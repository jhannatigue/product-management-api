<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->call->database();
        $this->call->library('api');
    }

    public function register()
    {
        $this->api->require_method('POST');

        $data = $this->api->body();

        $username = trim($data['username'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($username === '' || $email === '' || $password === '') {
            $this->api->respond_error(
                'Username, email, and password are required.',
                400
            );
        }

        $existing = $this->db->raw(
            "SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1",
            [$email, $username]
        );

        if ($existing->fetch(PDO::FETCH_ASSOC)) {
            $this->api->respond_error(
                'Username or email already exists.',
                409
            );
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $this->db->raw(
            "INSERT INTO users
             (username, email, password, role, is_active, created_at)
             VALUES (?, ?, ?, 'admin', 1, CURRENT_TIMESTAMP)",
            [
                $username,
                $email,
                $hashed_password
            ]
        );

        $this->api->respond([
            'status' => true,
            'message' => 'User registered successfully.'
        ], 201);
    }

    public function login()
    {
        $this->api->require_method('POST');

        $data = $this->api->body();

        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($email === '' || $password === '') {
            $this->api->respond_error(
                'Email and password are required.',
                400
            );
        }

        $stmt = $this->db->raw(
            "SELECT id, username, email, password, role, is_active
             FROM users
             WHERE email = ?
             LIMIT 1",
            [$email]
        );

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->api->respond_error(
                'Invalid email or password.',
                401
            );
        }

        if ((int) $user['is_active'] !== 1) {
            $this->api->respond_error(
                'This account is inactive.',
                403
            );
        }

        $role_scopes = [
            'admin' => ['read', 'write', 'delete'],
            'moderator' => ['read', 'write'],
            'user' => ['read']
        ];

        $tokens = $this->api->issue_tokens([
            'id' => $user['id'],
            'role' => $user['role'],
            'scopes' => $role_scopes[$user['role']] ?? ['read']
        ]);

        $this->api->respond([
            'status' => true,
            'message' => 'Login successful.',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'tokens' => $tokens
        ]);
    }
}