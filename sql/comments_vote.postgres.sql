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
