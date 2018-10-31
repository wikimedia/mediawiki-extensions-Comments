-- PostgreSQL variant of Comments' database schema
-- This is probably crazy, but so is PostgreSQL. I've never used PGSQL so
-- there's a fair chance that the code is full of bugs, stupid things or both.
-- Please feel free to submit patches or just go ahead and fix it.
--
-- Tested at SQLFiddle.com against PostgreSQL 8.3.20 & 9.1.9 and at least this
-- builds. Doesn't guarantee anything, though.
--
-- Author: Jack Phoenix
-- Date: 24 July 2013

DROP SEQUENCE IF EXISTS Comments_block_cb_id_seq CASCADE;
CREATE SEQUENCE Comments_block_cb_id_seq MINVALUE 0 START WITH 0;

CREATE TABLE Comments_block (
  cb_id INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('Comments_block_cb_id_seq'),
  cb_user_id INTEGER NOT NULL DEFAULT 0,
  cb_user_name TEXT NOT NULL DEFAULT '',
  cb_user_id_blocked INTEGER DEFAULT NULL,
  cb_user_name_blocked TEXT NOT NULL DEFAULT '',
  cb_date TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX cb_user_id ON Comments_block (cb_user_id);
