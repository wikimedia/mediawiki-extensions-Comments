-- PostgreSQL variant of Comments' database schema
-- This is probably crazy, but so is PostgreSQL. I've never used PGSQL so
-- there's a fair chance that the code is full of bugs, stupid things or both.
-- Please feel free to submit patches or just go ahead and fix it.
--
-- Tested at SQLFiddle.com against PostgreSQL 8.3.20 & 9.1.9 and at least this
-- builds. Doesn't guarantee anything, though.
--
-- Author: Jack Phoenix <jack@countervandalism.net>
-- Date: 24 July 2013
DROP SEQUENCE IF EXISTS Comments_CommentID_seq CASCADE;
CREATE SEQUENCE Comments_CommentID_seq MINVALUE 0 START WITH 0;

CREATE TABLE Comments (
  CommentID INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('Comments_CommentID_seq'),
  Comment_Page_ID INTEGER NOT NULL DEFAULT 0,
  Comment_user_id INTEGER NOT NULL DEFAULT 0,
  Comment_Username TEXT NOT NULL DEFAULT '',
  Comment_Text TEXT NOT NULL,
  Comment_Date TIMESTAMPTZ NOT NULL DEFAULT now(),
  Comment_Parent_ID INTEGER NOT NULL DEFAULT 0,
  Comment_IP TEXT NOT NULL DEFAULT '',
  Comment_Plus_Count INTEGER NOT NULL DEFAULT 0,
  Comment_Minus_Count INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX comment_page_id_index ON Comments (Comment_Page_ID);
CREATE INDEX wiki_user_id ON Comments (Comment_user_id);
CREATE INDEX wiki_user_name ON Comments (Comment_Username);
CREATE INDEX pluscontidx ON Comments (Comment_user_id);
CREATE INDEX miuscountidx ON Comments (Comment_Plus_Count);
CREATE INDEX comment_date ON Comments (Comment_Minus_Count);

CREATE TABLE Comments_Vote (
  Comment_Vote_ID INTEGER NOT NULL DEFAULT 0,
  Comment_Vote_user_id INTEGER NOT NULL DEFAULT 0,
  Comment_Vote_Username TEXT NOT NULL DEFAULT '',
  Comment_Vote_Score INTEGER NOT NULL DEFAULT 0,
  Comment_Vote_Date TIMESTAMPTZ NOT NULL DEFAULT now(),
  Comment_Vote_IP TEXT NOT NULL DEFAULT ''
);

CREATE UNIQUE INDEX Comments_Vote_user_id_index ON Comments_Vote (Comment_Vote_ID,Comment_Vote_Username);
CREATE INDEX Comment_Vote_Score ON Comments_Vote (Comment_Vote_Score);
CREATE INDEX Comment_Vote_user_id ON Comments_Vote (Comment_Vote_user_id);


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