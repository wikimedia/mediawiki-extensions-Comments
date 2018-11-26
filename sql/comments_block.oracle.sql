-- Oracle variant of Comments' database schema
-- This is probably crazy, but so is Oracle. I've never used Oracle so
-- there's a fair chance that the code is full of bugs, stupid things or both.
-- Please feel free to submit patches or just go ahead and fix it.
--
-- This DOES NOT build at SQLFiddle.com...
--
-- Author: Jack Phoenix
-- Date: 24 July 2013
-- No idea if this is needed, but /maintenance/oracle/tables.sql uses it, so I
-- guess it serves some purpose here, too
define mw_prefix='{$wgDBprefix}';

CREATE SEQUENCE Comments_block_cb_id_seq;

CREATE TABLE &mw_prefix.Comments_block (
  cb_id NUMBER NOT NULL,
  cb_user_id NUMBER NOT NULL DEFAULT 0,
  cb_user_name VARCHAR2(255) NOT NULL,
  cb_user_id_blocked NUMBER DEFAULT NULL,
  cb_user_name_blocked VARCHAR2(255) NOT NULL,
  cb_date TIMESTAMP(6) WITH TIME ZONE
);

CREATE INDEX &mw_prefix.cb_user_id ON &mw_prefix.Comments_block (cb_user_id);

ALTER TABLE &mw_prefix.Comments_block ADD CONSTRAINT &mw_prefix.Comments_block_pk PRIMARY KEY (cb_id);
