# Authentication API Endpoints

## Signup Endpoint

**POST** `/api/auth/signup.php`

Creates a new vendor account with pending status (requires admin approval).

### Request Body
```json
{
  "email": "vendor@example.com",
  "password": "password123"
}
```

### Response (Success - 200)
```json
{
  "status": "success",
  "message": "Account created successfully! Your account is pending approval. Please wait for an admin to activate your account.",
  "data": {
    "user": {
      "id": "uuid-here",
      "email": "vendor@example.com",
      "role": "vendor",
      "status": "pending",
      "created_at": "2024-01-01T12:00:00+00:00"
    }
  }
}
```

### Response (Error - 400)
```json
{
  "status": "error",
  "message": "Error message here"
}
```

### Validation Rules
- Email must be valid format
- Password must be at least 6 characters
- Email must be unique (not already registered)

---

## Login Endpoint

**POST** `/api/auth/login.php`

Authenticates user and returns user data with role and status.

### Request Body
```json
{
  "email": "vendor@example.com",
  "password": "password123"
}
```

### Response (Success - 200)
```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "user": {
      "id": "uuid-here",
      "email": "vendor@example.com",
      "role": "vendor",        // 'admin' or 'vendor'
      "status": "active"       // 'active', 'pending', or 'suspended'
    },
    "token": "session-token-here"
  }
}
```

### Response (Error - 400/403)

**Invalid Credentials (400):**
```json
{
  "status": "error",
  "message": "Invalid email or password"
}
```

**Account Pending (403):**
```json
{
  "status": "error",
  "message": "Your account is pending approval. Please wait for an admin to activate your account."
}
```

**Account Suspended (403):**
```json
{
  "status": "error",
  "message": "Your account has been suspended. Please contact support."
}
```

### Account Status Flow

1. **Pending**: New signups are set to pending status. Login is blocked until admin approves.
2. **Active**: Account is approved and can login successfully.
3. **Suspended**: Account is temporarily blocked. Login is denied.

### Role-Based Redirects (Frontend)

After successful login:
- If `role === "admin"` → Navigate to `/admin/dashboard`
- If `role === "vendor"` → Navigate to `/vendor/dashboard`

---

## Database Schema

The endpoints use the `users` table:

```sql
CREATE TABLE users (
    id VARCHAR(36) PRIMARY KEY,  -- UUID
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'vendor') NOT NULL DEFAULT 'vendor',
    status ENUM('active', 'pending', 'suspended') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## Example Usage (React/Axios)

### Signup
```javascript
const signup = async (email, password) => {
  const response = await axios.post('http://localhost/backend/api/auth/signup.php', {
    email,
    password
  });
  return response.data;
};
```

### Login
```javascript
const login = async (email, password) => {
  const response = await axios.post('http://localhost/backend/api/auth/login.php', {
    email,
    password
  });
  
  if (response.data.status === 'success') {
    const { user, token } = response.data.data;
    
    // Store token in localStorage or cookie
    localStorage.setItem('token', token);
    localStorage.setItem('user', JSON.stringify(user));
    
    // Redirect based on role
    if (user.role === 'admin') {
      window.location.href = '/admin/dashboard';
    } else if (user.role === 'vendor') {
      window.location.href = '/vendor/dashboard';
    }
  }
  
  return response.data;
};
```

---

## Security Notes

1. **Password Hashing**: Passwords are hashed using PHP's `password_hash()` with default algorithm (bcrypt).
2. **Email Validation**: Email is validated using PHP's `filter_var()` with `FILTER_VALIDATE_EMAIL`.
3. **SQL Injection**: Protected using prepared statements.
4. **CORS**: Configured in `config.php` to allow React frontend origins.
5. **Token**: Simple session token is generated. Consider implementing JWT for production.

