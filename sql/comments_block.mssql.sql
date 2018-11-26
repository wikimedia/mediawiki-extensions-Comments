-- Microsoft SQL Server (MSSQL) variant of Comments' database schema
-- This is probably crazy, but so is MSSQL. I've never used MSSQL so
-- there's a fair chance that the code is full of bugs, stupid things or both.
-- Please feel free to submit patches or just go ahead and fix it.
--
-- Tested at SQLFiddle.com against MS SQL Server 2008 & 2012 and at least this
-- builds. Doesn't guarantee anything, though.
--
-- Author: Jack Phoenix
-- Date: 24 July 2013

CREATE TABLE /*$wgDBprefix*/Comments_block (
  cb_id INT NOT NULL PRIMARY KEY IDENTITY(0,1),
  cb_user_id INT NOT NULL default 0,
  cb_user_name NVARCHAR(255) NOT NULL default '',
  cb_user_id_blocked INT default NULL,
  cb_user_name_blocked NVARCHAR(255) NOT NULL default '',
  cb_date DATETIME default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/cb_user_id ON /*$wgDBprefix*/Comments_block (cb_user_id);
