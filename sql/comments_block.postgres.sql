DROP SEQUENCE IF EXISTS "Comments_block_cb_id_seq" CASCADE;
CREATE SEQUENCE "Comments_block_cb_id_seq";

-- Need to quote the name so that it doesn't get normalized to lowercase
CREATE TABLE "Comments_block" (
  cb_id INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('Comments_block_cb_id_seq'),
  cb_actor INTEGER NOT NULL DEFAULT 0,
  cb_actor_blocked INTEGER DEFAULT NULL,
  cb_date TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS cb_actor ON "Comments_block" (cb_actor);
