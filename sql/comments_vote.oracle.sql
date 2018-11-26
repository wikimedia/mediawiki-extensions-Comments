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

CREATE TABLE &mw_prefix.Comments_Vote (
  Comment_Vote_ID NUMBER NOT NULL DEFAULT 0,
  Comment_Vote_user_id NUMBER NOT NULL DEFAULT 0,
  Comment_Vote_Username VARCHAR2(200) NOT NULL,
  Comment_Vote_Score NUMBER NOT NULL DEFAULT 0,
  Comment_Vote_Date TIMESTAMP(6) WITH TIME ZONE NOT NULL,
  Comment_Vote_IP VARCHAR2(45) NOT NULL
);

CREATE UNIQUE INDEX &mw_prefix.Comments_Vote_user_id_index ON &mw_prefix.Comments_Vote (Comment_Vote_ID,Comment_Vote_Username);
CREATE INDEX &mw_prefix.Comment_Vote_Score ON &mw_prefix.Comments_Vote (Comment_Vote_Score);
CREATE INDEX &mw_prefix.Comment_Vote_user_id ON &mw_prefix.Comments_Vote (Comment_Vote_user_id);
