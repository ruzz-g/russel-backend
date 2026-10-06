<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * AuthController
 *
 * Token-based authentication (JWT access token + refresh token) built on the
 * LavaLust API library. All responses go through $this->api->respond().
 */
class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
    }

    /**
     * POST /api/auth/register
     * Body: { "username": "...", "email": "...", "password": "..." }
     */
    public function register()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('register:' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 10, 60);

        $in       = $this->json_input();
        $username = trim((string) ($in['username'] ?? ''));
        $email    = strtolower(trim((string) ($in['email'] ?? '')));
        $password = (string) ($in['password'] ?? '');

        $errors = [];
        if (strlen($username) < 3 || strlen($username) > 100) {
            $errors['username'] = 'Username must be 3-100 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'A valid email is required.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if ($errors) {
            $this->api->respond(['error' => 'Validation failed', 'status' => 422, 'errors' => $errors], 422);
        }

        $exists = $this->db->table('users')
            ->where('email', $email)
            ->or_where('username', $username)
            ->get();
        if ($exists) {
            $this->api->respond_error('Username or email is already registered.', 409);
        }

        $this->db->table('users')->insert([
            'username' => $username,
            'email'    => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role'     => 'user',
        ]);
        $id = (int) $this->db->last_id();

        $this->api->respond([
            'message' => 'Registration successful.',
            'user'    => $this->public_user($this->find_user_by_id($id)),
        ], 201);
    }

    /**
     * POST /api/auth/login
     * Body: { "email": "...", "password": "..." }  (email field also accepts the username)
     */
    public function login()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('login:' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 10, 60);

        $in         = $this->json_input();
        $identifier = trim((string) ($in['email'] ?? $in['username'] ?? ''));
        $password   = (string) ($in['password'] ?? '');

        if ($identifier === '' || $password === '') {
            $this->api->respond_error('Email and password are required.', 422);
        }

        $user = $this->db->table('users')
            ->where('email', strtolower($identifier))
            ->or_where('username', $identifier)
            ->get();

        // Always run password_verify so response time does not reveal valid accounts.
        $hash  = $user['password'] ?? '$2y$10$usesomesillystringforeusesomesillystringfore.abcdefghijklmn';
        $valid = password_verify($password, $hash);

        if (!$user || !$valid || (int) $user['is_active'] !== 1) {
            $this->api->respond_error('Invalid credentials.', 401);
        }

        $tokens = $this->api->issue_tokens([
            'id'     => (int) $user['id'],
            'role'   => $user['role'],
            'scopes' => $this->scopes_for_role($user['role']),
        ]);

        $this->api->respond([
            'message' => 'Login successful.',
            'user'    => $this->public_user($user),
            'tokens'  => $tokens,
        ]);
    }

    /**
     * POST /api/auth/refresh
     * Body: { "refresh_token": "..." }
     */
    public function refresh()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('refresh:' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 30, 60);

        $in    = $this->json_input();
        $token = (string) ($in['refresh_token'] ?? '');
        if ($token === '') {
            $this->api->respond_error('refresh_token is required.', 422);
        }

        $this->api->refresh_access_token($token);
    }

    /**
     * POST /api/auth/logout   (requires Bearer access token)
     * Body: { "refresh_token": "..." }
     */
    public function logout()
    {
        $this->api->require_method('POST');
        $payload = $this->api->require_jwt();

        $in    = $this->json_input();
        $token = (string) ($in['refresh_token'] ?? '');
        if ($token !== '') {
            $this->api->revoke_refresh_token($token);
        } else {
            // No token supplied: revoke every refresh token of this user.
            $this->db->raw('DELETE FROM refresh_tokens WHERE user_id = ?', [(int) $payload['sub']]);
        }

        $this->api->respond(['message' => 'Logged out successfully.']);
    }

    /**
     * GET /api/auth/me   (requires Bearer access token)
     */
    public function me()
    {
        $this->api->require_method('GET');
        $payload = $this->api->require_jwt();

        $user = $this->find_user_by_id((int) $payload['sub']);
        if (!$user) {
            $this->api->respond_error('Unauthorized', 401);
        }

        $this->api->respond(['user' => $this->public_user($user)]);
    }

    // ------------------------------------------------------------------

    private function find_user_by_id($id)
    {
        return $this->db->table('users')->where('id', $id)->get();
    }

    private function public_user($user)
    {
        return [
            'id'         => (int) $user['id'],
            'username'   => $user['username'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'created_at' => $user['created_at'],
        ];
    }

    private function scopes_for_role($role)
    {
        $map = [
            'admin'     => ['read', 'write', 'delete'],
            'moderator' => ['read', 'write'],
            'user'      => ['read', 'write', 'delete'],
        ];
        return $map[$role] ?? ['read'];
    }

    /**
     * Read the raw JSON body. (The API library's body() HTML-escapes values,
     * which would corrupt passwords and product text, so it is not used here.)
     */
    private function json_input()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
}
