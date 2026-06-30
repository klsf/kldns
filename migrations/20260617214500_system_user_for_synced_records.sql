INSERT INTO users(id, group_id, status, username, password_hash, sid, email, points)
VALUES (0, 100, 0, 'system-sync', 'system-disabled', 'system-sync', NULL, 0)
ON CONFLICT(id) DO NOTHING;

-- +kldns Down
DELETE FROM users WHERE id = 0 AND username = 'system-sync';
