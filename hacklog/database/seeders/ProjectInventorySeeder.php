<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\MajorOffice;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProjectInventorySeeder extends Seeder
{
    /**
     * CSV names that already exist in Hacklog under a different title.
     * Keys are original spreadsheet names; values are current project names.
     */
    protected array $nameAliases = [
        '1909 Survey of Puerto Rican Elites' => 'PRSI: 1909 Survey of Puerto Rican Elites',
        'Advancing Research Methods and Scholarshipin Gun Injury Prevention (ARMS) First Step Firearm Storage Matching Tool' => 'First Step (ARMS)',
        'Faculty Workload' => 'Faculty Workload Tool',
        'Minor Protection Compliance Tool' => 'Minor Protection Platform',
        'QKit' => 'VPR Dashboards (QKit)',
        'Rodent Open Science Skeletal Archive (ROSSA)' => 'ROSSA',
        'School of Social Work Innovations Institute Project Management Tool' => 'School of Social Work Innovations Institute',
        'Student Activities Organization Compliance Database' => 'Student Activites Database',
        'US Animal Vaccine Research Coordination Network (USAVRCN) Member Database' => 'USAVRCN',
        'Well-Being Collective Website' => 'W-BC Website',
        'CCRLS Career Training CMS' => 'CCRLS: Career Plan Project',
        'Iglesias Foundation Oral Histories' => 'PRSI: Oral Histories',
        'Responsible Conduct of Research' => 'VPR Responsible Conduct of Research (RCR)',
        'Lincus 2.0 FAR Phase' => 'Lincus',
        'Lincus 2.0' => 'Lincus',
        'Academic Space Usage Tool' => 'Academic Space Planning Software',
        'Beyond Nuremberg' => 'Beyond Nuremberg Archive Access Plugin',
        'Botanical Conservatory Website' => 'EEB Botanical Conservatory Website & Database',
        'Center for Addiction Science and Innovation (CASI) Website' => 'CASI',
        'Center for Open Research Resources & Equipment (COR2E) Website' => 'CORE Website',
        'Connecticut Racial Profiling Prohibition Project' => 'CTRP3',
        'CT Children with Incarcerated Parents (CTCIP) Website' => 'CTCIP',
        'Early Childhood Intervention Personnel Center (ECIPC) Website Redesign' => 'ECIPC.org',
        'Faculty Travel Funding' => 'Faculty Travel Funds App',
        'FIT Tool' => 'FIT/MSIS Tool',
        'Greater Hartford Gives Foundation Centennial Timeline' => 'Hartford Foundation Centennial Timeline',
        'Haskins Lab Website' => 'Haskins',
        'Historical Index of American Clothing' => 'Visual Index of American Clothing, 1790-1930',
        'i3 Timetracker' => 'i3 Time Tracker',
        'Indoor Air Quality Initiative (AQI) Website' => 'Air Quality Initiative',
        'Institution Rankings Tool' => 'Rankings Website',
        'Life Design Workshops' => 'Life Design',
        'Outreach & Engagement Programs and Scholars Databases' => 'Outreach & Engagement',
        'OVPR Website Phase I' => 'OVPR Website',
        'Proteome-X' => 'Schwartz Lab / Proteome-X',
        "Provost's Operational Efficiency Toolkit" => "POET - Provost's Operational Efficiency Toolkit",
        'Provost Website' => "Provost's Office Website",
        'Rain Garden' => 'Rain Garden App',
        'Shipbuilding Initiative Website' => 'Shipbuilding Initiative',
        'Sing Sing Prison Museum Archive' => 'Sing Sing Prison Museum Oral Histories Archive',
        'Student Risk Flagging Dashboard' => 'Student Risk Flagging Dashboard 2026',
        'The Institution Cemetery Project' => 'Institution Cemeteries Project',
        'Academic Affairs Financial Dashboard' => 'Academic Affairs Financial Dashboards',
        'Caribbean Heritage Museum Illustrations' => 'Caribbean Heritage Museum',
    ];

    public function run(): void
    {
        $path = database_path('data/project-inventory.csv');

        if (!is_readable($path)) {
            $this->command?->error("Inventory CSV not found at {$path}");

            return;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $header = array_map(fn ($column) => trim((string) $column), $header ?: []);

        $existingByName = [];
        foreach (Project::all() as $project) {
            $existingByName[$this->normalizeName($project->name)] = $project;
        }

        $updated = 0;
        $created = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $record = $this->rowToRecord($header, $row);
            $csvName = trim((string) ($record['Project Name'] ?? ''));

            if ($csvName === '') {
                $skipped++;
                continue;
            }

            $name = $this->canonicalName($csvName);
            $normalized = $this->normalizeName($name);
            $classification = $this->classificationFromRecord($record);

            if (isset($existingByName[$normalized])) {
                $existingByName[$normalized]->fill(array_filter(
                    $classification,
                    fn ($value) => $value !== null
                ))->save();
                $updated++;
                continue;
            }

            $project = Project::create(array_merge($classification, [
                'name' => $name,
                'status' => $this->mapStatus($record['Status'] ?? ''),
                'staffing_model' => Project::STAFFING_DEDICATED,
            ]));
            $existingByName[$normalized] = $project;
            $created++;
        }

        fclose($handle);

        $this->command?->info("Project inventory seeded: {$updated} updated, {$created} created, {$skipped} skipped.");
    }

    protected function rowToRecord(array $header, array $row): array
    {
        $record = [];

        foreach ($header as $index => $column) {
            $record[$column] = isset($row[$index]) ? trim((string) $row[$index]) : '';
        }

        return $record;
    }

    protected function canonicalName(string $csvName): string
    {
        return $this->nameAliases[$csvName] ?? $csvName;
    }

    protected function normalizeName(string $name): string
    {
        $name = Str::lower(trim($name));
        $name = str_replace(['&', '’', "'"], ['and', '', ''], $name);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $name) ?? '');
    }

    protected function classificationFromRecord(array $record): array
    {
        $homeName = $this->cleanLookupName($record['Home Department'] ?? '');
        $nestedName = $this->cleanLookupName($record['Nested Department'] ?? '');
        $officeName = $this->cleanLookupName($record['Top Level School or Major Office'] ?? '');

        $department = $homeName !== '' ? $this->firstOrCreateHomeDepartment($homeName) : null;
        $nested = ($department && $nestedName !== '' && strcasecmp($nestedName, $homeName) !== 0)
            ? $this->firstOrCreateNestedDepartment($department, $nestedName)
            : null;
        $office = $officeName !== '' ? MajorOffice::firstOrCreate(['name' => $officeName]) : null;

        $launchDate = $this->parseLaunchDate($record['Launch Date'] ?? '');

        return [
            'project_type' => $this->mapProjectType($record['Project Type'] ?? ''),
            'department_id' => $department?->id,
            'nested_department_id' => $nested?->id,
            'major_office_id' => $office?->id,
            'client_pi' => $this->nullableString($record['Client/PI'] ?? ''),
            'client_category' => $this->mapClientCategory($record['Client Catergory'] ?? ''),
            'uconn_affiliation' => $this->mapAffiliation($record['UConn Affiliation'] ?? ''),
            'grant_value' => $this->parseGrantValue($record[' Grant Value '] ?? $record['Grant Value'] ?? ''),
            'sponsor' => $this->nullableString($record['Sponsor'] ?? ''),
            'launch_date' => $launchDate,
        ];
    }

    protected function firstOrCreateHomeDepartment(string $name): Department
    {
        return Department::firstOrCreate(
            ['name' => $name, 'parent_id' => null],
            ['name' => $name, 'parent_id' => null]
        );
    }

    protected function firstOrCreateNestedDepartment(Department $home, string $name): Department
    {
        return Department::firstOrCreate(
            ['name' => $name, 'parent_id' => $home->id],
            ['name' => $name, 'parent_id' => $home->id]
        );
    }

    protected function mapStatus(string $raw): string
    {
        $value = Str::lower($raw);

        return match (true) {
            str_contains($value, 'sunset') => Project::STATUS_ARCHIVED,
            str_contains($value, 'launched') => Project::STATUS_COMPLETED,
            str_contains($value, 'discovery'),
            str_contains($value, 'pending'),
            str_contains($value, 'in review') => Project::STATUS_PLANNING,
            default => Project::STATUS_ACTIVE,
        };
    }

    protected function mapProjectType(string $raw): ?string
    {
        $value = Str::lower(trim($raw));

        return match ($value) {
            'website' => Project::TYPE_WEBSITE,
            'webapp' => Project::TYPE_WEBAPP,
            'graphic design' => Project::TYPE_GRAPHIC_DESIGN,
            'program' => Project::TYPE_PROGRAM,
            'other' => Project::TYPE_OTHER,
            default => null,
        };
    }

    protected function mapClientCategory(string $raw): ?string
    {
        $value = Str::lower(trim($raw));

        return match (true) {
            $value === '' => null,
            str_contains($value, 'administrative') => Project::CLIENT_CATEGORY_ADMINISTRATIVE,
            str_contains($value, 'center') || str_contains($value, 'institute') => Project::CLIENT_CATEGORY_CENTER,
            str_contains($value, 'fee-based') || str_contains($value, 'fee based') => Project::CLIENT_CATEGORY_FEE_BASED,
            str_contains($value, 'school') || str_contains($value, 'college') || str_contains($value, 'academic') => Project::CLIENT_CATEGORY_SCHOOL,
            default => null,
        };
    }

    protected function mapAffiliation(string $raw): ?string
    {
        $value = Str::lower(trim($raw));

        return match ($value) {
            'internal' => Project::AFFILIATION_INTERNAL,
            'external' => Project::AFFILIATION_EXTERNAL,
            default => null,
        };
    }

    protected function parseGrantValue(string $raw): ?string
    {
        $digits = preg_replace('/[^0-9.]/', '', $raw) ?? '';

        if ($digits === '' || $digits === '.') {
            return null;
        }

        return number_format((float) $digits, 2, '.', '');
    }

    protected function parseLaunchDate(string $raw): ?string
    {
        $raw = trim($raw);

        if ($raw === '' || preg_match('/[a-z]/i', $raw)) {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function cleanLookupName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        $name = rtrim($name, '?');

        return $name;
    }

    protected function nullableString(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
