CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_expiration_index" on "cache"("expiration");
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_locks_expiration_index" on "cache_locks"("expiration");
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "phases"(
  "id" integer primary key autoincrement not null,
  "project_id" integer not null,
  "name" varchar not null,
  "description" text,
  "status" varchar check("status" in('planned', 'active', 'completed')) not null default 'planned',
  "start_date" date,
  "end_date" date,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("project_id") references "projects"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "columns"(
  "id" integer primary key autoincrement not null,
  "project_id" integer not null,
  "name" varchar not null,
  "position" integer not null,
  "is_default" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("project_id") references "projects"("id") on delete cascade
);
CREATE UNIQUE INDEX "columns_project_id_position_unique" on "columns"(
  "project_id",
  "position"
);
CREATE TABLE IF NOT EXISTS "project_resources"(
  "id" integer primary key autoincrement not null,
  "project_id" integer not null,
  "title" varchar not null,
  "type" varchar check("type" in('link', 'note')) not null,
  "url" varchar,
  "content" text,
  "position" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("project_id") references "projects"("id") on delete cascade
);
CREATE INDEX "project_resources_project_id_position_index" on "project_resources"(
  "project_id",
  "position"
);
CREATE TABLE IF NOT EXISTS "task_user"(
  "id" integer primary key autoincrement not null,
  "task_id" integer not null,
  "user_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("task_id") references "tasks"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "task_user_task_id_user_id_unique" on "task_user"(
  "task_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "role" varchar check("role" in('admin', 'team', 'client')) not null default 'team',
  "active" tinyint(1) not null default '1',
  "netid" varchar,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "nicknames" text
);
CREATE UNIQUE INDEX "users_new_email_unique" on "users"("email");
CREATE UNIQUE INDEX "users_new_netid_unique" on "users"("netid");
CREATE TABLE IF NOT EXISTS "project_shares"(
  "id" integer primary key autoincrement not null,
  "project_id" integer not null,
  "shareable_type" varchar not null,
  "shareable_id" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("project_id") references "projects"("id") on delete cascade
);
CREATE UNIQUE INDEX "project_shares_project_id_shareable_type_shareable_id_unique" on "project_shares"(
  "project_id",
  "shareable_type",
  "shareable_id"
);
CREATE INDEX "project_shares_shareable_type_shareable_id_index" on "project_shares"(
  "shareable_type",
  "shareable_id"
);
CREATE TABLE IF NOT EXISTS "task_comments"(
  "id" integer primary key autoincrement not null,
  "task_id" integer not null,
  "user_id" integer not null,
  "body" text not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("task_id") references "tasks"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "task_activities"(
  "id" integer primary key autoincrement not null,
  "task_id" integer not null,
  "user_id" integer,
  "action" varchar not null,
  "metadata" text,
  "created_at" datetime not null,
  foreign key("task_id") references "tasks"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "project_activities"(
  "id" integer primary key autoincrement not null,
  "project_id" integer not null,
  "user_id" integer,
  "action" varchar not null,
  "metadata" text,
  "created_at" datetime not null,
  foreign key("project_id") references "projects"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "task_attachments"(
  "id" integer primary key autoincrement not null,
  "task_id" integer not null,
  "user_id" integer not null,
  "filename" varchar not null,
  "original_name" varchar not null,
  "mime_type" varchar not null,
  "size" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("task_id") references "tasks"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "tasks"(
  "id" integer primary key autoincrement not null,
  "phase_id" integer,
  "column_id" integer not null,
  "title" varchar not null,
  "description" text,
  "position" integer,
  "start_date" date,
  "due_date" date,
  "created_at" datetime,
  "updated_at" datetime,
  "created_by" integer,
  "updated_by" integer,
  "completed_at" datetime,
  "status" varchar not null default 'planned',
  "priority" varchar,
  "weight" varchar,
  foreign key("updated_by") references users("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete set null on update no action,
  foreign key("column_id") references columns("id") on delete cascade on update no action,
  foreign key("phase_id") references phases("id") on delete cascade on update no action
);
CREATE TABLE IF NOT EXISTS "project_favorites"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "project_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("project_id") references "projects"("id") on delete cascade
);
CREATE UNIQUE INDEX "project_favorites_user_id_project_id_unique" on "project_favorites"(
  "user_id",
  "project_id"
);
CREATE INDEX "project_favorites_user_id_created_at_index" on "project_favorites"(
  "user_id",
  "created_at"
);
CREATE TABLE IF NOT EXISTS "projects"(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR NOT NULL,
  description TEXT,
  status VARCHAR NOT NULL DEFAULT 'active' CHECK(status IN('planning', 'active', 'on_hold', 'completed', 'archived')),
  staffing_model VARCHAR NOT NULL DEFAULT 'dedicated' CHECK(staffing_model IN('dedicated', 'shared')),
  launch_date DATE,
  created_at DATETIME,
  updated_at DATETIME
  ,
  "slack_webhook_url" varchar,
  "slack_channel_id" varchar,
  "slack_bot_enabled" tinyint(1) not null default '0'
);
CREATE TABLE IF NOT EXISTS "tags"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "color" varchar,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "tags_slug_unique" on "tags"("slug");
CREATE TABLE IF NOT EXISTS "project_tag"(
  "id" integer primary key autoincrement not null,
  "project_id" integer not null,
  "tag_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("project_id") references "projects"("id") on delete cascade,
  foreign key("tag_id") references "tags"("id") on delete cascade
);
CREATE UNIQUE INDEX "project_tag_project_id_tag_id_unique" on "project_tag"(
  "project_id",
  "tag_id"
);
CREATE INDEX "project_tag_tag_id_index" on "project_tag"("tag_id");
CREATE TABLE IF NOT EXISTS "project_intakes"(
  "id" integer primary key autoincrement not null,
  "project_id" integer not null,
  "user_id" integer,
  "source_type" varchar not null default 'manual',
  "source_content" text not null,
  "status" varchar not null default 'queued',
  "model" varchar,
  "ollama_summary" text,
  "error_message" text,
  "processing_started_at" datetime,
  "processing_completed_at" datetime,
  "correlation_id" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "provider" varchar,
  "slack_context" text,
  foreign key("project_id") references "projects"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE INDEX "project_intakes_project_id_created_at_index" on "project_intakes"(
  "project_id",
  "created_at"
);
CREATE INDEX "project_intakes_status_index" on "project_intakes"("status");
CREATE TABLE IF NOT EXISTS "project_intake_proposals"(
  "id" integer primary key autoincrement not null,
  "project_intake_id" integer not null,
  "title" varchar not null,
  "description" text,
  "suggested_phase_id" integer,
  "suggested_assignee_id" integer,
  "due_date" date,
  "confidence" numeric,
  "source_excerpt" text,
  "possible_duplicate_of" varchar,
  "status" varchar not null default 'pending',
  "disposition_reason" varchar,
  "created_task_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("project_intake_id") references "project_intakes"("id") on delete cascade,
  foreign key("suggested_phase_id") references "phases"("id") on delete set null,
  foreign key("suggested_assignee_id") references "users"("id") on delete set null,
  foreign key("created_task_id") references "tasks"("id") on delete set null
);
CREATE INDEX "project_intake_proposals_project_intake_id_status_index" on "project_intake_proposals"(
  "project_intake_id",
  "status"
);
CREATE TABLE IF NOT EXISTS "task_dependencies"(
  "id" integer primary key autoincrement not null,
  "task_id" integer not null,
  "dependency_id" integer not null,
  foreign key("task_id") references "tasks"("id") on delete cascade,
  foreign key("dependency_id") references "tasks"("id") on delete cascade
);
CREATE UNIQUE INDEX "task_dependencies_task_id_dependency_id_unique" on "task_dependencies"(
  "task_id",
  "dependency_id"
);
CREATE INDEX "task_dependencies_dependency_id_index" on "task_dependencies"(
  "dependency_id"
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2026_02_07_141254_create_projects_table',1);
INSERT INTO migrations VALUES(5,'2026_02_07_141756_create_phases_table',1);
INSERT INTO migrations VALUES(6,'2026_02_07_142316_create_columns_table',1);
INSERT INTO migrations VALUES(7,'2026_02_07_142918_create_tasks_table',1);
INSERT INTO migrations VALUES(8,'2026_02_07_151328_add_dates_to_tasks_table',1);
INSERT INTO migrations VALUES(9,'2026_02_07_154255_update_task_status_values_to_planning_semantics',1);
INSERT INTO migrations VALUES(10,'2026_02_07_163921_add_role_and_active_to_users_table',1);
INSERT INTO migrations VALUES(11,'2026_02_07_183702_add_netid_to_users_table',1);
INSERT INTO migrations VALUES(12,'2026_02_07_220000_create_project_resources_table',1);
INSERT INTO migrations VALUES(13,'2026_02_07_230000_create_task_user_table',1);
INSERT INTO migrations VALUES(14,'2026_02_08_001403_update_user_roles_for_visibility',1);
INSERT INTO migrations VALUES(15,'2026_02_08_001438_create_project_shares_table',1);
INSERT INTO migrations VALUES(16,'2026_02_08_014339_make_phase_id_nullable_in_tasks_table',1);
INSERT INTO migrations VALUES(17,'2026_02_08_165852_create_task_comments_table',1);
INSERT INTO migrations VALUES(18,'2026_02_09_000001_add_tracking_fields_to_tasks_table',1);
INSERT INTO migrations VALUES(19,'2026_02_09_000002_create_task_activities_table',1);
INSERT INTO migrations VALUES(20,'2026_02_09_000003_create_project_activities_table',1);
INSERT INTO migrations VALUES(21,'2026_02_11_000001_create_task_attachments_table',1);
INSERT INTO migrations VALUES(22,'2026_02_14_144450_convert_task_status_to_string',1);
INSERT INTO migrations VALUES(23,'2026_02_20_000001_create_project_favorites_table',1);
INSERT INTO migrations VALUES(24,'2026_02_27_210943_add_staffing_model_to_projects_table',1);
INSERT INTO migrations VALUES(25,'2026_03_09_132701_update_project_statuses_to_include_planning_and_completed',1);
INSERT INTO migrations VALUES(26,'2026_05_22_000001_add_priority_and_weight_to_tasks_table',2);
INSERT INTO migrations VALUES(27,'2026_07_15_235500_create_tags_table',2);
INSERT INTO migrations VALUES(28,'2026_07_15_235501_create_project_tag_table',2);
INSERT INTO migrations VALUES(29,'2026_07_16_120000_add_slack_webhook_url_to_projects_table',2);
INSERT INTO migrations VALUES(30,'2026_08_13_000001_create_project_intakes_table',2);
INSERT INTO migrations VALUES(31,'2026_08_13_000002_create_project_intake_proposals_table',2);
INSERT INTO migrations VALUES(32,'2026_08_13_000003_add_provider_to_project_intakes_table',2);
INSERT INTO migrations VALUES(33,'2026_08_13_000004_add_slack_bot_fields_to_projects_table',2);
INSERT INTO migrations VALUES(34,'2026_08_13_000005_add_slack_context_to_project_intakes_table',2);
INSERT INTO migrations VALUES(35,'2026_08_19_141254_create_task_dependency_pivot',2);
INSERT INTO migrations VALUES(36,'2026_08_19_152422_add_nicknames_to_users_table',2);
INSERT INTO migrations VALUES(37,'2026_08_24_165021_dependency_indexes',2);
INSERT INTO migrations VALUES(38,'2026_08_24_200000_add_launch_date_to_projects_table',3);
