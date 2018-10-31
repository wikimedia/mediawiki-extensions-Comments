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
  Comment_IP TEXT NOT NULL DEFAULT ''
);

CREATE INDEX comment_page_id_index ON Comments (Comment_Page_ID);
CREATE INDEX wiki_user_id ON Comments (Comment_user_id);
CREATE INDEX wiki_user_name ON Comments (Comment_Username);
