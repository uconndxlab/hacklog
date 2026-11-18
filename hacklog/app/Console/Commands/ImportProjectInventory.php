<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\MajorOffice;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportProjectInventory extends Command
{
    protected $signature = 'project-inventory:import
        {--file=database/data/Project Inventory Mapped.csv : CSV to import; expected to contain a Hacklog ID column}
        {--apply : Write the planned changes to the database; without this option the command is a dry run}
        {--report=database/data/Project Inventory Import Preview.csv : CSV report of planned or applied changes}';

    protected $description = 'Import supported project inventory fields into existing Hacklog projects by ID';

    private const PRIMARY_NETIDS = [
        'BK' => 'bak11004', 'Brian' => 'bpd01001', 'Brian Daley' => 'bpd01001', 'Brooke' => 'bef10003',
        'Dan' => 'das10009', 'Jeff' => 'jdw01001', 'Joel' => 'jrs06005', 'Maggie' => 'mmd21011',
        'Natalie' => 'nml17005', 'Phoebe' => 'phl19002', 'Sara' => 'sas16119', 'Sue' => 'sls02010',
    ];

    private array $reportRows = [];
    private array $fieldCounts = [];
    private int $matchedRows = 0;
    private int $skippedRows = 0;
    private int $changedProjects = 0;

    public function handle(): int
    {
        $file = $this->resolvePath($this->option('file'));
        $reportPath = $this->resolvePath($this->option('report'));

        if (! is_readable($file)) {
            $this->error("Inventory file is not readable: {$file}");

            return self::FAILURE;
        }

        try {
            $rows = $this->readCsv($file);
        } catch (Throwable $exception) {
            $this->error('Could not read inventory CSV: '.$exception->getMessage());

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Applying project inventory updates...' : 'Dry run: no database changes will be made.');

        try {
            if ($apply) {
                DB::transaction(function () use ($rows): void {
                    foreach ($rows as [$rowNumber, $record]) {
                        $this->processRow($rowNumber, $record, true);
                    }
                });
            } else {
                foreach ($rows as [$rowNumber, $record]) {
                    $this->processRow($rowNumber, $record, false);
                }
            }
        } catch (Throwable $exception) {
            $this->error('Import stopped and database transaction was rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        try {
            $this->writeReport($reportPath);
        } catch (Throwable $exception) {
            $this->error('Could not write the import report: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(['Result', 'Count'], [
            ['Rows mapped to existing projects', $this->matchedRows],
            ['Projects with changes', $this->changedProjects],
            ['Rows skipped', $this->skippedRows],
        ]);
        foreach ($this->fieldCounts as $field => $count) {
            $this->line("  {$field}: {$count} project(s)");
        }
        $this->line("Report: {$reportPath}");
        $this->info($apply ? 'Import complete.' : 'Dry run complete. Pass --apply to write these changes.');

        return self::SUCCESS;
    }

    private function processRow(int $rowNumber, array $record, bool $apply): void
    {
        $projectId = filter_var(trim((string) ($record['Hacklog ID'] ?? '')), FILTER_VALIDATE_INT);
        $projectName = trim((string) ($record['Project Name'] ?? ''));
        if (! $projectId) {
            $this->skippedRows++;
            $this->reportRows[] = $this->reportRow($rowNumber, $projectName, '', 'skipped', '', 'Missing or invalid Hacklog ID.');

            return;
        }

        $project = Project::query()->with(['department', 'nestedDepartment', 'majorOffice'])->find($projectId);
        if (! $project) {
            $this->skippedRows++;
            $this->reportRows[] = $this->reportRow($rowNumber, $projectName, $projectId, 'skipped', '', 'Hacklog ID does not exist.');

            return;
        }
        $this->matchedRows++;

        $attributes = [];
        $changes = [];
        $warnings = [];
        $this->planScalar($record, $project, $attributes, $changes, $warnings);
        $this->planDepartments($record, $project, $attributes, $changes, $warnings, $apply);
        $leader = $this->planLeader($record, $project, $warnings);

        if ($leader && $leader['change']) {
            $changes['i3 Primary'] = $leader['user']->name.' (project leader)';
        }

        if ($changes === []) {
            $this->reportRows[] = $this->reportRow($rowNumber, $projectName, $projectId, 'no change', '', implode(' ', $warnings));

            return;
        }

        $this->changedProjects++;
        foreach (array_keys($changes) as $field) {
            $this->fieldCounts[$field] = ($this->fieldCounts[$field] ?? 0) + 1;
        }

        if ($apply) {
            if ($attributes !== []) {
                $project->fill($attributes);
                $project->save();
            }
            if ($leader && $leader['change']) {
                $project->shares()
                    ->where('shareable_type', 'user')
                    ->where('is_leader', true)
                    ->update(['is_leader' => false]);
                $project->shares()->updateOrCreate(
                    ['shareable_type' => 'user', 'shareable_id' => (string) $leader['user']->id],
                    ['is_leader' => true]
                );
            }
            ProjectActivity::log($project->id, null, 'updated', [
                'source' => 'project_inventory_import',
                'fields' => array_keys($changes),
            ]);
        }

        $this->reportRows[] = $this->reportRow(
            $rowNumber,
            $projectName,
            $projectId,
            $apply ? 'updated' : 'would update',
            json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            implode(' ', $warnings)
        );
    }

    private function planScalar(array $record, Project $project, array &$attributes, array &$changes, array &$warnings): void
    {
        $status = trim((string) ($record['Status'] ?? ''));
        if ($status !== '') {
            $desired = $this->mapStatus($status);
            $this->setValue($attributes, $changes, 'Status', 'status', $desired, $project->status);
        }

        $type = trim((string) ($record['Project Type'] ?? ''));
        if ($type !== '') {
            $desired = $this->mapProjectType($type);
            if ($desired === null) {
                $warnings[] = "Unrecognized Project Type '{$type}' was ignored.";
            } else {
                $this->setValue($attributes, $changes, 'Project Type', 'project_type', $desired, $project->project_type);
            }
        }

        $launchDate = trim((string) ($record['Launch Date'] ?? ''));
        if ($launchDate !== '') {
            $desired = $this->parseLaunchDate($launchDate);
            if ($desired === null) {
                $warnings[] = "Unparseable Launch Date '{$launchDate}' was ignored.";
            } else {
                $current = $project->launch_date?->toDateString();
                $this->setValue($attributes, $changes, 'Launch Date', 'launch_date', $desired, $current);
            }
        }

        $clientPi = trim((string) ($record['Client/PI'] ?? ''));
        if ($clientPi !== '') {
            $this->setValue($attributes, $changes, 'Client/PI', 'client_pi', $clientPi, $project->client_pi);
        }

        $category = trim((string) ($record['Client Catergory'] ?? ''));
        if ($category !== '') {
            $desired = $this->mapClientCategory($category);
            if ($desired === null) {
                $warnings[] = "Unrecognized Client Catergory '{$category}' was ignored.";
            } else {
                $this->setValue($attributes, $changes, 'Client Catergory', 'client_category', $desired, $project->client_category);
            }
        }

        $affiliation = strtolower(trim((string) ($record['UConn Affiliation'] ?? '')));
        if ($affiliation !== '') {
            if (! in_array($affiliation, ['internal', 'external'], true)) {
                $warnings[] = "Unrecognized UConn Affiliation '{$affiliation}' was ignored.";
            } else {
                $this->setValue($attributes, $changes, 'UConn Affiliation', 'uconn_affiliation', $affiliation, $project->uconn_affiliation);
            }
        }

        $lane = strtolower(trim((string) ($record['Lane'] ?? '')));
        if ($lane !== '') {
            if (! in_array($lane, ['internal', 'core'], true)) {
                $warnings[] = "Unrecognized Lane '{$lane}' was ignored.";
            } else {
                $this->setValue($attributes, $changes, 'Lane', 'lane', $lane, $project->lane);
            }
        }

        $hasGrant = $this->parseHasGrant(trim((string) ($record['Grant Y/N'] ?? '')));
        if ($hasGrant !== null) {
            $this->setValue($attributes, $changes, 'Grant Y/N', 'has_grant', $hasGrant, (bool) $project->has_grant);
        }

        $grantRaw = trim((string) ($record['Grant Value'] ?? ''));
        if ($grantRaw !== '') {
            $grantValue = $this->parseGrantValue($grantRaw);
            if ($grantValue === null) {
                $warnings[] = "Grant Value '{$grantRaw}' could not be parsed and was ignored.";
            } else {
                $current = $project->grant_value !== null ? (float) $project->grant_value : null;
                $this->setValue($attributes, $changes, 'Grant Value', 'grant_value', $grantValue, $current);
                if ($hasGrant === null && $grantValue > 0) {
                    $this->setValue($attributes, $changes, 'Grant Value implies grant', 'has_grant', true, (bool) $project->has_grant);
                }
            }
        }

        $sponsor = trim((string) ($record['Sponsor'] ?? ''));
        if ($sponsor !== '') {
            $this->setValue($attributes, $changes, 'Sponsor', 'sponsor', $sponsor, $project->sponsor);
        }
    }

    private function planDepartments(array $record, Project $project, array &$attributes, array &$changes, array &$warnings, bool $apply): void
    {
        $homeName = $this->cleanLookupName($record['Home Department'] ?? '');
        $nestedName = $this->cleanLookupName($record['Nested Department'] ?? '');
        $officeName = $this->cleanLookupName($record['Top Level School or Major Office'] ?? '');

        $home = null;
        $homeWillBeCreated = false;
        if ($homeName !== '') {
            $home = $this->findDepartment($homeName, null);
            if ($home) {
                if ($project->department_id !== $home->id && $this->normalizeName($project->department?->name) !== $this->normalizeName($homeName)) {
                    $attributes['department_id'] = $home->id;
                    $changes['Home Department'] = $homeName;
                }
            } elseif ($this->normalizeName($project->department?->name) !== $this->normalizeName($homeName)) {
                if ($apply) {
                    $home = Department::firstOrCreate(['name' => $homeName, 'parent_id' => null]);
                    $attributes['department_id'] = $home->id;
                } else {
                    $homeWillBeCreated = true;
                }
                $changes['Home Department'] = $homeName.' (create)';
            } else {
                $home = $project->department;
            }
        } else {
            $home = $project->department;
        }

        if ($nestedName !== '') {
            if ($homeName !== '' && strcasecmp($nestedName, $homeName) === 0) {
                if ($project->nested_department_id !== null) {
                    $attributes['nested_department_id'] = null;
                    $changes['Nested Department'] = '(clear; same as Home Department)';
                }
            } else {
                $parentId = $home?->id ?? ($homeWillBeCreated ? null : $project->department_id);
                $nested = $parentId ? $this->findDepartment($nestedName, $parentId) : null;
                if ($nested) {
                    if ($project->nested_department_id !== $nested->id) {
                        $attributes['nested_department_id'] = $nested->id;
                        $changes['Nested Department'] = $nestedName;
                    }
                } elseif ($homeWillBeCreated || $this->normalizeName($project->nestedDepartment?->name) !== $this->normalizeName($nestedName)
                    || ($home && $project->nestedDepartment?->parent_id !== $home->id)) {
                    if ($apply && $parentId) {
                        $nested = Department::firstOrCreate(['name' => $nestedName, 'parent_id' => $parentId]);
                        $attributes['nested_department_id'] = $nested->id;
                    }
                    $changes['Nested Department'] = $nestedName.($homeWillBeCreated ? ' (create under new Home Department)' : ' (create)');
                    if (! $parentId && ! $homeWillBeCreated) {
                        $warnings[] = "Nested Department '{$nestedName}' needs a Home Department before it can be imported.";
                    }
                }
            }
        }

        if ($officeName !== '') {
            $office = MajorOffice::query()->get()->first(fn ($candidate) => $this->normalizeName($candidate->name) === $this->normalizeName($officeName));
            if ($office) {
                if ($project->major_office_id !== $office->id && $this->normalizeName($project->majorOffice?->name) !== $this->normalizeName($officeName)) {
                    $attributes['major_office_id'] = $office->id;
                    $changes['Top Level School or Major Office'] = $officeName;
                }
            } elseif ($this->normalizeName($project->majorOffice?->name) !== $this->normalizeName($officeName)) {
                if ($apply) {
                    $office = MajorOffice::firstOrCreate(['name' => $officeName]);
                    $attributes['major_office_id'] = $office->id;
                }
                $changes['Top Level School or Major Office'] = $officeName.' (create)';
            }
        }
    }

    private function planLeader(array $record, Project $project, array &$warnings): ?array
    {
        $label = trim((string) ($record['i3 Primary'] ?? ''));
        if ($label === '') {
            return null;
        }
        $netid = self::PRIMARY_NETIDS[$label] ?? null;
        if (! $netid) {
            $warnings[] = "i3 Primary '{$label}' is not in the known label-to-NetID mapping and was ignored.";

            return null;
        }
        $user = User::query()->where('netid', $netid)->first();
        if (! $user) {
            $warnings[] = "No Hacklog user exists for i3 Primary '{$label}' ({$netid}); leader was not changed.";

            return null;
        }
        $current = $project->shares()->where('shareable_type', 'user')->where('is_leader', true)->value('shareable_id');

        return ['user' => $user, 'change' => (string) $current !== (string) $user->id];
    }

    private function setValue(array &$attributes, array &$changes, string $label, string $field, mixed $desired, mixed $current): void
    {
        if ($desired === $current) {
            return;
        }
        $attributes[$field] = $desired;
        $changes[$label] = ['from' => $current, 'to' => $desired];
    }

    private function findDepartment(string $name, ?int $parentId): ?Department
    {
        return Department::query()->where('parent_id', $parentId)->get()
            ->first(fn (Department $department) => $this->normalizeName($department->name) === $this->normalizeName($name));
    }

    private function normalizeName(?string $name): string
    {
        $name = Str::ascii(mb_strtolower(trim((string) $name)));
        $name = str_replace('&', ' and ', $name);

        return preg_replace('/[^a-z0-9]+/', '', $name) ?? '';
    }

    private function cleanLookupName(mixed $value): string
    {
        return rtrim(preg_replace('/\s+/', ' ', trim((string) $value)) ?? '', '?');
    }

    private function mapStatus(string $raw): string
    {
        $value = Str::lower($raw);

        return match (true) {
            str_contains($value, 'sunset') => Project::STATUS_ARCHIVED,
            str_contains($value, 'launched') => Project::STATUS_COMPLETED,
            str_contains($value, 'discovery'), str_contains($value, 'pending'), str_contains($value, 'in review') => Project::STATUS_PLANNING,
            default => Project::STATUS_ACTIVE,
        };
    }

    private function mapProjectType(string $raw): ?string
    {
        return match (Str::lower(trim($raw))) {
            'website' => Project::TYPE_WEBSITE,
            'webapp' => Project::TYPE_WEBAPP,
            'graphic design' => Project::TYPE_GRAPHIC_DESIGN,
            'program' => Project::TYPE_PROGRAM,
            'other' => Project::TYPE_OTHER,
            default => null,
        };
    }

    private function mapClientCategory(string $raw): ?string
    {
        $value = Str::lower(trim($raw));

        return match (true) {
            str_contains($value, 'administrative') => Project::CLIENT_CATEGORY_ADMINISTRATIVE,
            str_contains($value, 'center'), str_contains($value, 'institute') => Project::CLIENT_CATEGORY_CENTER,
            str_contains($value, 'fee-based'), str_contains($value, 'fee based') => Project::CLIENT_CATEGORY_FEE_BASED,
            str_contains($value, 'school'), str_contains($value, 'college'), str_contains($value, 'academic') => Project::CLIENT_CATEGORY_SCHOOL,
            default => null,
        };
    }

    private function parseHasGrant(string $raw): ?bool
    {
        if ($raw === '') return null;
        $value = Str::lower(trim($raw));
        if (in_array($value, ['y','yes','true','1'], true)) return true;
        if (in_array($value, ['n','no','false','0'], true)) return false;

        return null;
    }

    private function parseGrantValue(string $raw): ?float
    {
        if (Str::lower(trim($raw)) === 'unknown') {
            return 0.0;
        }
        $digits = preg_replace('/[^0-9.]/', '', $raw) ?? '';
        if ($digits === '' || $digits === '.') return null;

        return round((float) $digits, 2);
    }

    private function parseLaunchDate(string $raw): ?string
    {
        if (preg_match('/[a-z]/i', $raw)) return null;
        try {
            return Carbon::parse($raw)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) throw new RuntimeException('Could not open CSV.');
        $headers = fgetcsv($handle);
        if (! is_array($headers)) throw new RuntimeException('CSV has no header row.');
        $headers = array_map(fn ($header) => trim((string) $header, "\xEF\xBB\xBF \t\n\r\0\x0B"), $headers);
        if (! in_array('Hacklog ID', $headers, true)) throw new RuntimeException('CSV is missing the Hacklog ID column; use Project Inventory Mapped.csv.');
        $rows = [];
        $rowNumber = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($values === [null] || $values === []) continue;
            $values = array_pad($values, count($headers), '');
            $rows[] = [$rowNumber, array_combine($headers, array_slice($values, 0, count($headers)))];
        }
        fclose($handle);

        return $rows;
    }

    private function resolvePath(string $path): string
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }

    private function reportRow(int $row, string $name, int|string $id, string $action, string $changes, string $warnings): array
    {
        return ['CSV Row' => $row, 'Project Name' => $name, 'Hacklog ID' => $id, 'Action' => $action, 'Changes' => $changes, 'Warnings' => $warnings];
    }

    private function writeReport(string $path): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Could not create report directory: {$directory}");
        }
        $handle = fopen($path, 'w');
        if ($handle === false) throw new RuntimeException("Could not write report: {$path}");
        fputcsv($handle, ['CSV Row', 'Project Name', 'Hacklog ID', 'Action', 'Changes', 'Warnings']);
        foreach ($this->reportRows as $row) fputcsv($handle, $row);
        fclose($handle);
    }
}
