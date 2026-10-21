CREATE SCHEMA admin;

CREATE TABLE admin.roles (
    key TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    permissions JSONB NOT NULL,
    visible INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL
);

CREATE TABLE admin.users (
    id UUID PRIMARY KEY,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    email TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    password_hash_algo TEXT NOT NULL,
    role TEXT NOT NULL REFERENCES admin.roles (key),
    is_active INTEGER NOT NULL DEFAULT 1,
    is_deleted INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL
);

CREATE TABLE public.lookup_table (
    id UUID PRIMARY KEY,
    key TEXT NOT NULL,
    name TEXT NOT NULL,
    slug TEXT NOT NULL,
    parent_id UUID REFERENCES public.lookup_table (id),
    metadata JSONB,
    is_active INTEGER NOT NULL DEFAULT 1,
    is_deleted INTEGER NOT NULL DEFAULT 0
);

INSERT INTO admin.roles (key, title, permissions, created_at) VALUES
    ('editor', 'Editor', '["cms-edit","forms-view"]', '2026-01-01 12:00:00'),
    ('reader', 'Reader', '["cms-view"]', '2026-02-01 12:00:00');

INSERT INTO admin.users (id, first_name, last_name, email, password_hash, password_hash_algo, role, is_active, is_deleted, created_at) VALUES
    ('11111111-1111-4111-8111-111111111111', 'Alice', 'Editor', 'alice@example.test', 'fixture-password-hash', 'argon2id', 'editor', 1, 0, '2026-01-01 12:00:00'),
    ('22222222-2222-4222-8222-222222222222', 'Bob', 'Reader', 'bob@example.test', 'fixture-password-hash', 'argon2id', 'reader', 1, 0, '2026-01-02 12:00:00'),
    ('33333333-3333-4333-8333-333333333333', 'Deleted', 'Editor', 'deleted@example.test', 'fixture-password-hash', 'argon2id', 'editor', 1, 1, '2026-01-03 12:00:00'),
    ('44444444-4444-4444-8444-444444444444', 'Inactive', 'Editor', 'inactive@example.test', 'fixture-password-hash', 'argon2id', 'editor', 0, 0, '2026-01-04 12:00:00');

INSERT INTO public.lookup_table (id, key, name, slug, parent_id, metadata, is_deleted) VALUES
    ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'lt:root', 'Root', 'root', NULL, '{}', 0),
    ('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'lt:child-ten', 'Child 10', 'child-ten', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', '{"rank":10,"enabled":true}', 0),
    ('cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'lt:child-two', 'Child 2', 'child-two', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', NULL, 0),
    ('dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'lt:deleted', 'Deleted', 'deleted', 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', '{}', 1),
    ('eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee', 'lt:other', 'Other root', 'other', NULL, '{}', 0);
