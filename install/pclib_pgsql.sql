-- Table structure for table LOOKUPS (TPL)

CREATE TABLE lookups (
  guid serial NOT NULL,
  id character varying(10) DEFAULT NULL,
  app character varying(10) DEFAULT NULL,
  cname character varying(20) DEFAULT NULL,
  label character varying(100) DEFAULT NULL,
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
  label character varying(80) DEFAULT NULL,
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
  rval character varying(20) DEFAULT '0'
);

CREATE UNIQUE INDEX i_auth_register_role ON auth_register USING btree (role_id,obj_id,right_id);
CREATE UNIQUE INDEX i_auth_register_user ON auth_register USING btree (user_id,obj_id,right_id);

-- Table structure for table AUTH_RIGHTS

-- CREATE TYPE permission_t AS ENUM ('B','C','I');

CREATE TABLE auth_rights (
  id serial NOT NULL,
  sname character varying(100) DEFAULT NULL,
  annot character varying(100) DEFAULT NULL,
  rtype character varying(1) DEFAULT 'B',
  dt timestamp without time zone,
  CONSTRAINT pk_auth_rights_id PRIMARY KEY (id)
);

-- Table structure for table AUTH_ROLES

CREATE TABLE auth_roles (
  id serial NOT NULL,
  sname character varying(100) DEFAULT NULL,
  annot character varying(100) DEFAULT NULL,
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
  username character varying(30) DEFAULT NULL,
  fullname character varying(100) DEFAULT NULL,
  email character varying(100) DEFAULT NULL,
  passw character varying(255) DEFAULT NULL,
  dpassw character varying(50) DEFAULT NULL,
  annot character varying(100) DEFAULT NULL,
  last_login timestamp without time zone,
  loginfail smallint DEFAULT 0,
  active smallint DEFAULT 1,
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
  label character varying(80) DEFAULT NULL,
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
  label character varying(50) DEFAULT NULL,
  level smallint DEFAULT NULL,
  route character varying(50) DEFAULT NULL,
  url character varying(100) DEFAULT NULL,
  rkey character varying(50) DEFAULT NULL,
  nr integer DEFAULT NULL,
  active smallint DEFAULT 1,
  CONSTRAINT pk_tree_lookups_id PRIMARY KEY (id)
);

-- Table structure for table FILESTORAGE

CREATE TABLE filestorage (
  id serial NOT NULL,
  filepath character varying(255) DEFAULT NULL,
  origname character varying(255) DEFAULT NULL,
  annot character varying(255) DEFAULT NULL,
  entity_id integer DEFAULT NULL,
  file_id character varying(100) DEFAULT NULL,
  entity_type character varying(100) DEFAULT NULL,
  mimetype character varying(255) DEFAULT NULL,
  size integer DEFAULT NULL,
  user_id integer DEFAULT NULL,
  dt timestamp without time zone,
  CONSTRAINT pk_filestorage_id PRIMARY KEY (id)
);
