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

CREATE SEQUENCE Comments_CommentID_seq;

CREATE TABLE &mw_prefix.Comments (
  CommentID NUMBER NOT NULL,
  Comment_Page_ID NUMBER NOT NULL DEFAULT 0,
  Comment_user_id NUMBER NOT NULL DEFAULT 0,
  Comment_Username VARCHAR2(200) NOT NULL,
  -- CLOB (original MySQL one uses text), as per http://stackoverflow.com/questions/1180204/oracle-equivalent-of-mysqls-text-type
  Comment_Text CLOB NOT NULL,
  Comment_Date TIMESTAMP(6) WITH TIME ZONE NOT NULL,
  Comment_Parent_ID NUMBER NOT NULL DEFAULT 0,
  Comment_IP VARCHAR2(45) NOT NULL,
);

CREATE INDEX &mw_prefix.comment_page_id_index ON &mw_prefix.Comments (Comment_Page_ID);
CREATE INDEX &mw_prefix.wiki_user_id ON &mw_prefix.Comments (Comment_user_id);
CREATE INDEX &mw_prefix.wiki_user_name ON &mw_prefix.Comments (Comment_Username);

ALTER TABLE &mw_prefix.Comments ADD CONSTRAINT &mw_prefix.Comments_pk PRIMARY KEY (CommentID);
