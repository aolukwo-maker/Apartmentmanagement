USE apartment_management;

INSERT INTO roles (role_id, role_name)
VALUES
    (1, 'Admin'),
    (2, 'Manager'),
    (3, 'Tenant'),
    (4, 'Caretaker'),
    (5, 'Security')
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name);
