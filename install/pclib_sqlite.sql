-- Table structure for table LOOKUPS (TPL)

CREATE TABLE LOOKUPS (
  GUID integer primary key,
  ID varchar(50),
  APP varchar(50),
  CNAME varchar(100),
  LABEL varchar(255),
  POSITION integer DEFAULT 0
);

CREATE INDEX I_LOOKUPS_CNAME ON LOOKUPS (CNAME ASC);

/*Table structure for table `TRANSLATOR` */

CREATE TABLE `TRANSLATOR` (
  ID  integer primary key,
  TRANSLATOR integer,
  LANG integer,
  PAGE integer,
  TEXT_ID integer DEFAULT 0,
  TSTEXT ntext,
  DT datetime
);

/*Table structure for table `TRANSLATOR_LABELS` */

CREATE TABLE TRANSLATOR_LABELS (
  ID integer primary key,
  LABEL varchar(100),
  CATEGORY integer DEFAULT 0,
  DT datetime
);

CREATE INDEX I_TRANSLATOR_LABELS_LABEL ON TRANSLATOR_LABELS (LABEL);

-- Table structure for table AUTH_REGISTER

CREATE TABLE AUTH_REGISTER (
  USER_ID integer,
  ROLE_ID integer,
  OBJ_ID integer DEFAULT 0,
  RIGHT_ID integer,
  RVAL varchar(50) DEFAULT 0
);

CREATE UNIQUE INDEX I_AUTH_REGISTER_ROLE ON AUTH_REGISTER (ROLE_ID,OBJ_ID,RIGHT_ID);
CREATE UNIQUE INDEX I_AUTH_REGISTER_USER ON AUTH_REGISTER (USER_ID,OBJ_ID,RIGHT_ID);


-- Table structure for table AUTH_RIGHTS

CREATE TABLE AUTH_RIGHTS (
  ID integer primary key,
  SNAME varchar(100),
  ANNOT varchar(255),
  RTYPE char(1) DEFAULT 'B',
  DT datetime
);

-- Table structure for table AUTH_ROLES 

CREATE TABLE AUTH_ROLES (
  ID integer primary key,
  SNAME varchar(100),
  ANNOT varchar(255),
  AUTHOR_ID integer,
  LASTMOD datetime,
  DT datetime
);

-- Table structure for table AUTH_USER_ROLE 

CREATE TABLE AUTH_USER_ROLE (
  USER_ID integer,
  ROLE_ID integer,
  OBJ_ID integer DEFAULT 0,
  R_PRIORITY integer DEFAULT 1
);

CREATE UNIQUE INDEX I_AUTH_USER_ROLE_USER_ID ON AUTH_USER_ROLE (USER_ID,ROLE_ID,OBJ_ID);
CREATE INDEX I_AUTH_USER_ROLE_ROLE_ID ON AUTH_USER_ROLE (ROLE_ID);


-- Table structure for table AUTH_USERS 

CREATE TABLE AUTH_USERS (
  ID integer primary key,
  USERNAME varchar(255),
  FULLNAME varchar(255),
  EMAIL varchar(255),
  PASSW varchar(255),
  DPASSW varchar(50),
  ANNOT varchar(255),
  LAST_LOGIN datetime,
  LOGINFAIL integer DEFAULT 0,
  ACTIVE integer DEFAULT 1,
  AUTHOR_ID integer,
  LASTMOD datetime,
  DT datetime,
  IP integer
);

CREATE UNIQUE INDEX I_AUTH_USERS_USERNAME ON AUTH_USERS (USERNAME);

-- Table structure for table LOGGER

CREATE TABLE LOGGER (
  ID integer primary key,
  LOGGER integer,
  ACTION integer,
  CATEGORY integer,
  USER_ID integer DEFAULT 0,
  ITEM_ID integer,
  IP integer,
  UA integer,
  DT datetime
);

CREATE INDEX I_LOGGER_USER_ID ON LOGGER (USER_ID);
CREATE INDEX I_LOGGER_DT ON LOGGER (DT);
CREATE INDEX I_LOGGER_ACTION ON LOGGER (ACTION);

-- Table structure for table LOGGER_LABELS

CREATE TABLE LOGGER_LABELS (
  ID integer primary key,
  LABEL varchar(100),
  CATEGORY integer DEFAULT 0,
  DT datetime
);

CREATE INDEX I_LOGGER_LABELS_LABEL ON LOGGER_LABELS (LABEL);

-- Table structure for table LOGGER_MESSAGES

CREATE TABLE LOGGER_MESSAGES (
  LOG_ID primary key,
  LOGGER integer,  
  MESSAGE ntext,
  DT datetime
);

-- Table structure for table TREE_LOOKUPS (APP) 
CREATE TABLE TREE_LOOKUPS (
  ID integer primary key,
  TREE_ID integer,
  LABEL VARCHAR(100),
  LEVEL integer,
  URL VARCHAR(255),
  ROUTE VARCHAR(100),
  RKEY varchar(100),
  NR integer,
  ACTIVE integer DEFAULT 1
);

CREATE INDEX TREE_LOOKUPS_NR ON TREE_LOOKUPS (TREE_ID,NR);


-- Table structure for table APP_PARAMS

CREATE TABLE APP_PARAMS (
  ID integer primary key,
  PARAM_NAME VARCHAR(100) DEFAULT NULL,
  PARAM_VALUE VARCHAR(255) DEFAULT NULL,
  TITLE VARCHAR(255) DEFAULT NULL,
  CREATED_AT datetime,
  UPDATED_AT datetime,
  AUTHOR_ID integer DEFAULT NULL
);

-- Table structure for table `FILESTORAGE`

CREATE TABLE FILESTORAGE (
  ID integer primary key,
  FILEPATH VARCHAR(255),
  ORIGNAME VARCHAR(255),
  ANNOT VARCHAR(255),
  HASH VARCHAR(16),
  ENTITY_ID integer,
  FILE_ID VARCHAR(100),
  ENTITY_TYPE VARCHAR(100),
  MIMETYPE VARCHAR(255),
  SIZE INT(11),
  USER_ID integer,
  DT datetime
);

CREATE INDEX I_FILESTORAGE_ENTITY  ON FILESTORAGE (ENTITY_TYPE,ENTITY_ID);
CREATE INDEX I_FILESTORAGE_HASH ON FILESTORAGE (HASH);

-- Table structure for table `PCLIB_MAILS`

CREATE TABLE PCLIB_MAILS (
  ID INTEGER PRIMARY KEY,
  "FROM" TEXT,
  "TO" TEXT,
  RECIPIENTS TEXT,
  SUBJECT TEXT,
  BODY TEXT,
  BODY_TEXT TEXT,
  STATUS INTEGER,
  ATTACHMENTS TEXT,
  TEMPLATE_NAME TEXT,
  SEND_AT DATETIME,
  CREATED_AT DATETIME
);

-- Table structure for table `PCLIB_CONTENT`

CREATE TABLE PCLIB_CONTENT (
  ID INTEGER PRIMARY KEY,
  NAME TEXT,
  CATEGORY TEXT,
  TITLE TEXT,
  BODY TEXT,
  CREATED_AT DATETIME,
  UPDATED_AT DATETIME,
  AUTHOR_ID INTEGER
);

-- Table structure for table `jobs` (padmin)

CREATE TABLE jobs (
  id integer primary key,
  name VARCHAR(100),
  annotation ntext,
  job_command VARCHAR(255),
  job_params VARCHAR(255),
  first_run_at datetime,
  period integer,
  last_run_at datetime,
  last_run_result ntext,
  last_run_duration numeric,
  active integer DEFAULT 1,
  created_at datetime,
  author_id integer
);

-- Fill lookups
INSERT INTO LOOKUPS (APP, ID, CNAME, LABEL, POSITION) VALUES ('padmin', 0, 'job-period', 'Ruční spuštění', 1);
INSERT INTO LOOKUPS (APP, ID, CNAME, LABEL, POSITION) VALUES ('padmin', 600, 'job-period', 'Jednou za 10 minut', 3);
INSERT INTO LOOKUPS (APP, ID, CNAME, LABEL, POSITION) VALUES ('padmin', 3600, 'job-period', 'Jednou za hodinu', 4);
INSERT INTO LOOKUPS (APP, ID, CNAME, LABEL, POSITION) VALUES ('padmin', 7200, 'job-period', 'Jednou za 2 hodiny', 5);
INSERT INTO LOOKUPS (APP, ID, CNAME, LABEL, POSITION) VALUES ('padmin', 86400, 'job-period', 'Jednou za den', 6);
INSERT INTO LOOKUPS (APP, ID, CNAME, LABEL, POSITION) VALUES ('padmin', 604800, 'job-period', 'Jednou za týden', 7);
INSERT INTO LOOKUPS (APP, ID, CNAME, LABEL, POSITION) VALUES ('padmin', 2592000, 'job-period', 'Jednou za měsíc', 8);
INSERT INTO LOOKUPS (APP, ID, CNAME, LABEL, POSITION) VALUES ('padmin', 60, 'job-period', 'Jednou za minutu', 2);

insert into TRANSLATOR_LABELS (ID, LABEL, CATEGORY) values(1,'App',1);

-- Version of PCLIB database structures.
INSERT INTO APP_PARAMS (PARAM_NAME, PARAM_VALUE, TITLE, CREATED_AT) VALUES ('PCLIB_VERSION', '3.2.0', 'Version of PCLIB database structures', datetime('now'));
