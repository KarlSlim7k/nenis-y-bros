-- Fix password hashes for all users
-- Password: Password123!
UPDATE usuarios SET password_hash = '$2y$10$3N2VSsK2Dpospd2pQEi9aOvLUcLud1supqTE1/vRBgiW1ZRrh9NpG';
