-- Microsoft SQL Server (MSSQL) variant of Comments' database schema
-- This is probably crazy, but so is MSSQL. I've never used MSSQL so
-- there's a fair chance that the code is full of bugs, stupid things or both.
-- Please feel free to submit patches or just go ahead and fix it.
--
-- Tested at SQLFiddle.com against MS SQL Server 2008 & 2012 and at least this
-- builds. Doesn't guarantee anything, though.
--
-- Author: Jack Phoenix <jack@countervandalism.net>
-- Date: 24 July 2013
CREATE TABLE /*$wgDBprefix*/Comments (
  CommentID INT NOT NULL PRIMARY KEY IDENTITY(0,1),
  Comment_Page_ID INT NOT NULL default 0,
  Comment_user_id INT NOT NULL default 0,
  Comment_Username NVARCHAR(200) NOT NULL default '',
  Comment_Text text NOT NULL,
  Comment_Date DATETIME NOT NULL default '0000-00-00 00:00:00',
  Comment_Parent_ID INT NOT NULL default 0,
  Comment_IP NVARCHAR(45) NOT NULL default '',
  Comment_Plus_Count INT NOT NULL default 0,
  Comment_Minus_Count INT NOT NULL default 0
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/comment_page_id_index ON /*$wgDBprefix*/Comments (Comment_Page_ID);
CREATE INDEX /*i*/wiki_user_id ON /*$wgDBprefix*/Comments (Comment_user_id);
CREATE INDEX /*i*/wiki_user_name ON /*$wgDBprefix*/Comments (Comment_Username);
CREATE INDEX /*i*/pluscontidx ON /*$wgDBprefix*/Comments (Comment_user_id);
CREATE INDEX /*i*/miuscountidx ON /*$wgDBprefix*/Comments (Comment_Plus_Count);
CREATE INDEX /*i*/comment_date ON /*$wgDBprefix*/Comments (Comment_Minus_Count);

CREATE TABLE /*$wgDBprefix*/Comments_Vote (
  Comment_Vote_ID INT NOT NULL default 0,
  Comment_Vote_user_id INT NOT NULL default 0,
  Comment_Vote_Username NVARCHAR(200) NOT NULL default '',
  Comment_Vote_Score INT NOT NULL default 0,
  Comment_Vote_Date DATETIME NOT NULL default '0000-00-00 00:00:00',
  Comment_Vote_IP NVARCHAR(45) NOT NULL default ''
) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/Comments_Vote_user_id_index ON /*$wgDBprefix*/Comments_Vote (Comment_Vote_ID,Comment_Vote_Username);
CREATE INDEX /*i*/Comment_Vote_Score ON /*$wgDBprefix*/Comments_Vote (Comment_Vote_Score);
CREATE INDEX /*i*/Comment_Vote_user_id ON /*$wgDBprefix*/Comments_Vote (Comment_Vote_user_id);

CREATE TABLE /*$wgDBprefix*/Comments_block (
  cb_id INT NOT NULL PRIMARY KEY IDENTITY(0,1),
  cb_user_id INT NOT NULL default 0,
  cb_user_name NVARCHAR(255) NOT NULL default '',
  cb_user_id_blocked INT default NULL,
  cb_user_name_blocked NVARCHAR(255) NOT NULL default '',
  cb_date DATETIME default NULL
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/cb_user_id ON /*$wgDBprefix*/Comments_block (cb_user_id);