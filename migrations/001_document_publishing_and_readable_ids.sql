ALTER TABLE documents ADD COLUMN readable_id TEXT;
ALTER TABLE documents ADD COLUMN publish_at TEXT;

CREATE UNIQUE INDEX idx_documents_readable_id ON documents(readable_id);
CREATE INDEX idx_documents_title ON documents(title);
CREATE INDEX idx_documents_publish_at ON documents(publish_at);
