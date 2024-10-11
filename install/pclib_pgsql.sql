-- Table structure for table LOOKUPS (TPL)

CREATE TABLE lookups (
  guid serial NOT NULL,
  id character varying(50) DEFAULT NULL,
  app character varying(50) DEFAULT NULL,
  cname character varying(100) DEFAULT NULL,
  label character varying(255) DEFAULT NULL,
  position integer DEFAULT 0,
  CONSTRAINT pk_lookups_guid PRIMARY KEY (guid)
);

CREATE INDEX i_cname ON lookups USING btree (cname);

-- Table structure for table `TRANSLATOR`

CREATE TABLE `translator` (
  id serial NOT NULL,
  translator integer DEFAULT NULL,
  lang integer DEFAULT 0,
  page integer DEFAULT NULL,
  text_id integer DEFAULT 0,
  tstext text DEFAULT NULL,
  dt timestamp without time zone,
  CONSTRAINT pk_translator_id PRIMARY KEY (id)
);

-- Table structure for table `TRANSLATOR_LABELS`

CREATE TABLE translator_labels (
  id serial NOT NULL,
  label character varying(100) DEFAULT NULL,
  category smallint DEFAULT 0,
  dt timestamp without time zone,
  CONSTRAINT pk_translator_labels_id PRIMARY KEY (id)
);

CREATE INDEX i_logger_labels_label ON logger_labels USING btree (label);

-- Table structure for table AUTH_REGISTER

CREATE TABLE auth_register (
  user_id integer DEFAULT NULL,
  role_id integer DEFAULT NULL,
  obj_id integer DEFAULT 0,
  right_id integer DEFAULT NULL,
  rval character varying(50) DEFAULT '0'
);

CREATE UNIQUE INDEX i_auth_register_role ON auth_register USING btree (role_id,obj_id,right_id);
CREATE UNIQUE INDEX i_auth_register_user ON auth_register USING btree (user_id,obj_id,right_id);

-- Table structure for table AUTH_RIGHTS

-- CREATE TYPE permission_t AS ENUM ('B','C','I');

CREATE TABLE auth_rights (
  id serial NOT NULL,
  sname character varying(100) DEFAULT NULL,
  annot character varying(255) DEFAULT NULL,
  rtype character varying(1) DEFAULT 'B',
  dt timestamp without time zone,
  CONSTRAINT pk_auth_rights_id PRIMARY KEY (id)
);

-- Table structure for table AUTH_ROLES

CREATE TABLE auth_roles (
  id serial NOT NULL,
  sname character varying(100) DEFAULT NULL,
  annot character varying(255) DEFAULT NULL,
  author_id integer DEFAULT NULL,
  lastmod timestamp without time zone,
  dt timestamp without time zone,
  CONSTRAINT pk_auth_roles_id PRIMARY KEY (id)
);

-- Table structure for table AUTH_USER_ROLE

CREATE TABLE auth_user_role (
  user_id integer DEFAULT NULL,
  role_id integer DEFAULT NULL,
  obj_id integer DEFAULT '0',
  r_priority integer DEFAULT '1'
);

CREATE UNIQUE INDEX i_auth_user_role_user_id ON auth_user_role USING btree (user_id,role_id,obj_id);
CREATE INDEX i_auth_user_role_role_id ON auth_user_role USING btree (role_id);

-- Table structure for table AUTH_USERS

CREATE TABLE auth_users (
  id serial NOT NULL,
  username character varying(255) DEFAULT NULL,
  fullname character varying(255) DEFAULT NULL,
  email character varying(255) DEFAULT NULL,
  passw character varying(255) DEFAULT NULL,
  dpassw character varying(50) DEFAULT NULL,
  annot character varying(255) DEFAULT NULL,
  last_login timestamp without time zone,
  loginfail smallint DEFAULT 0,
  active smallint DEFAULT 1,
  author_id integer DEFAULT NULL,
  lastmod timestamp without time zone,
  dt timestamp without time zone,
  ip integer DEFAULT NULL,
  CONSTRAINT pk_auth_users_id PRIMARY KEY (id)
);

CREATE UNIQUE INDEX i_auth_users_username ON auth_users USING btree (username);

-- Table structure for table LOGGER

CREATE TABLE logger (
  id serial NOT NULL,
  logger smallint DEFAULT NULL,
  action smallint DEFAULT NULL,
  category smallint,
  user_id integer DEFAULT 0,
  item_id integer DEFAULT NULL,
  ip integer DEFAULT NULL,
  ua smallint DEFAULT NULL,
  dt timestamp without time zone,
  CONSTRAINT pk_logger_id PRIMARY KEY (id)
);

CREATE INDEX i_logger_user_id ON logger USING btree (user_id);
CREATE INDEX i_logger_dt ON logger USING btree (dt);
CREATE INDEX i_logger_action ON logger USING btree (action);

-- Table structure for table LOGGER_LABELS

CREATE TABLE logger_labels (
  id serial NOT NULL,
  label character varying(100) DEFAULT NULL,
  category smallint DEFAULT 0,
  dt timestamp without time zone,
  CONSTRAINT pk_logger_labels_id PRIMARY KEY (id)
);

CREATE INDEX i_logger_labels_label ON logger_labels USING btree (label);

-- Table structure for table LOGGER_MESSAGES

CREATE TABLE logger_messages (
  log_id integer NOT NULL,
  logger smallint DEFAULT NULL,
  message text DEFAULT NULL,
  dt timestamp without time zone,
  CONSTRAINT pk_logger_messages_log_id PRIMARY KEY (log_id)
);

-- Table structure for table TREE_LOOKUPS

CREATE TABLE tree_lookups (
  id serial NOT NULL,
  tree_id integer NOT NULL,
  label character varying(100) DEFAULT NULL,
  level smallint DEFAULT NULL,
  route character varying(100) DEFAULT NULL,
  url character varying(255) DEFAULT NULL,
  rkey character varying(100) DEFAULT NULL,
  nr integer DEFAULT NULL,
  active smallint DEFAULT 1,
  CONSTRAINT pk_tree_lookups_id PRIMARY KEY (id)
);

-- Table structure for table APP_PARAMS

CREATE TABLE app_params (
  id serial NOT NULL,
  param_name character varying(100) DEFAULT NULL,
  param_value character varying(255) DEFAULT NULL,
  title character varying(255) DEFAULT NULL,
  created_at timestamp without time zone,
  updated_at timestamp without time zone,
  author_id integer DEFAULT NULL,
  CONSTRAINT pk_app_params_id PRIMARY KEY (id)
);

-- Table structure for table FILESTORAGE

CREATE TABLE filestorage (
  id serial NOT NULL,
  filepath character varying(255) DEFAULT NULL,
  origname character varying(255) DEFAULT NULL,
  annot character varying(255) DEFAULT NULL,
  hash character varying(16) DEFAULT NULL,
  entity_id integer DEFAULT NULL,
  file_id character varying(100) DEFAULT NULL,
  entity_type character varying(100) DEFAULT NULL,
  mimetype character varying(255) DEFAULT NULL,
  size integer DEFAULT NULL,
  user_id integer DEFAULT NULL,
  dt timestamp without time zone,
  CONSTRAINT pk_filestorage_id PRIMARY KEY (id)
);

CREATE INDEX i_filestorage_entity ON filestorage USING btree (entity_type, entity_id);
CREATE INDEX i_filestorage_hash ON filestorage USING btree (hash);

CREATE TABLE jobs (
  id serial NOT NULL,
  name character varying(100) DEFAULT NULL,
  annotation text,
  job_command character varying(255) DEFAULT NULL,
  job_params character varying(255) DEFAULT NULL,
  first_run_at timestamp without time zone,
  period integer DEFAULT NULL,
  last_run_at timestamp without time zone,
  last_run_result text,
  last_run_duration decimal(10,2) DEFAULT NULL,
  active smallint DEFAULT 1,
  created_at timestamp without time zone,
  author_id integer DEFAULT NULL,
  CONSTRAINT pk_jobs_id PRIMARY KEY (id)
);

-- Fill lookups.
INSERT INTO lookups (app, id, cname, label, position) VALUES ('padmin', 0, 'job-period', 'Ruční spuštění', 1);
INSERT INTO lookups (app, id, cname, label, position) VALUES ('padmin', 600, 'job-period', 'Jednou za 10 minut', 3);
INSERT INTO lookups (app, id, cname, label, position) VALUES ('padmin', 3600, 'job-period', 'Jednou za hodinu', 4);
INSERT INTO lookups (app, id, cname, label, position) VALUES ('padmin', 7200, 'job-period', 'Jednou za 2 hodiny', 5);
INSERT INTO lookups (app, id, cname, label, position) VALUES ('padmin', 86400, 'job-period', 'Jednou za den', 6);
INSERT INTO lookups (app, id, cname, label, position) VALUES ('padmin', 604800, 'job-period', 'Jednou za týden', 7);
INSERT INTO lookups (app, id, cname, label, position) VALUES ('padmin', 2592000, 'job-period', 'Jednou za měsíc', 8);
INSERT INTO lookups (app, id, cname, label, position) VALUES ('padmin', 60, 'job-period', 'Jednou za minutu', 2);

insert into translator_labels (id, label, category) values(1,'App',1);

-- Version of PCLIB database structures.
INSERT INTO app_params (param_name, param_value, title, created_at) VALUES ('PCLIB_VERSION', '3.1.0', 'Version of PCLIB database structures', NOW());
