# User Registration API Guide

## Overview

The registration system supports two types of users with different required fields:

1. **Regular Users** - Full profile with detailed information
2. **Admin Users** - Minimal information (name, email, password, role)

---

## API Endpoint

```
POST /api/register
```

---

## Regular User Registration

### Required Fields

- `first_name` (string, max 255)
- `last_name` (string, max 255)
- `email` (string, unique, valid email)
- `password` (string, min 8 characters)
- `password_confirmation` (string, must match password)
- `whatsapp_number` (string, max 20)
- `city` (string, max 255)
- `state` (string, max 255)
- `country` (string, max 255)
- `profession` (string, max 255)
- `gender` (enum: `male`, `female`, `other`, `prefer_not_to_say`)
- `age` (integer, min 1, max 150)
- `educational_qualification` (string, max 255)
- `role` (optional, defaults to `user`)

### Example Request

```json
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john.doe@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "whatsapp_number": "+1234567890",
  "city": "New York",
  "state": "New York",
  "country": "United States",
  "profession": "Software Engineer",
  "gender": "male",
  "age": 30,
  "educational_qualification": "Bachelor's Degree",
  "role": "user"
}
```

### Example Response

```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "first_name": "John",
      "last_name": "Doe",
      "email": "john.doe@example.com",
      "role": "user",
      "whatsapp_number": "+1234567890",
      "city": "New York",
      "state": "New York",
      "country": "United States",
      "profession": "Software Engineer",
      "gender": "male",
      "age": 30,
      "educational_qualification": "Bachelor's Degree",
      "created_at": "2025-11-13T05:34:20.000000Z",
      "updated_at": "2025-11-13T05:34:20.000000Z"
    },
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  },
  "status": 201,
  "message": "User registered successfully"
}
```

---

## Admin Registration

### Required Fields

- `name` (string, max 255)
- `email` (string, unique, valid email)
- `password` (string, min 8 characters)
- `password_confirmation` (string, must match password)
- `role` (must be `admin`)

### Example Request

```json
{
  "name": "Admin User",
  "email": "admin@example.com",
  "password": "securepassword123",
  "password_confirmation": "securepassword123",
  "role": "admin"
}
```

### Example Response

```json
{
  "data": {
    "user": {
      "id": 2,
      "name": "Admin User",
      "email": "admin@example.com",
      "role": "admin",
      "created_at": "2025-11-13T05:34:20.000000Z",
      "updated_at": "2025-11-13T05:34:20.000000Z"
    },
    "token": "2|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  },
  "status": 201,
  "message": "Admin registered successfully"
}
```

---

## Validation Rules

### Common Rules (Both User Types)
- `email`: Required, must be valid email format, must be unique
- `password`: Required, minimum 8 characters, must be confirmed
- `role`: Optional, must be either `admin` or `user` (defaults to `user`)

### Regular User Specific Rules
- All profile fields are **required** for regular users
- `gender`: Must be one of: `male`, `female`, `other`, `prefer_not_to_say`
- `age`: Must be between 1 and 150

### Admin Specific Rules
- Only `name` is required in addition to common fields

---

## Error Responses

### Validation Error (422)

```json
{
  "data": [],
  "status": 422,
  "message": "Validation failed",
  "errors": {
    "first_name": ["The first name field is required."],
    "email": ["The email has already been taken."],
    "password": ["The password confirmation does not match."]
  }
}
```

### Example: Missing Required Fields

```json
{
  "data": [],
  "status": 422,
  "message": "Validation failed",
  "errors": {
    "first_name": ["The first name field is required."],
    "last_name": ["The last name field is required."],
    "whatsapp_number": ["The whatsapp number field is required."],
    "city": ["The city field is required."],
    "state": ["The state field is required."],
    "country": ["The country field is required."],
    "profession": ["The profession field is required."],
    "gender": ["The gender field is required."],
    "age": ["The age field is required."],
    "educational_qualification": ["The educational qualification field is required."]
  }
}
```

---

## Frontend Implementation Examples

### React/JavaScript Example

```javascript
// Register Regular User
async function registerUser(userData) {
  try {
    const response = await fetch('http://your-api-url/api/register', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        first_name: userData.firstName,
        last_name: userData.lastName,
        email: userData.email,
        password: userData.password,
        password_confirmation: userData.passwordConfirmation,
        whatsapp_number: userData.whatsappNumber,
        city: userData.city,
        state: userData.state,
        country: userData.country,
        profession: userData.profession,
        gender: userData.gender,
        age: userData.age,
        educational_qualification: userData.educationalQualification,
        role: 'user'
      })
    });

    const result = await response.json();
    
    if (result.status === 201) {
      // Store token for authenticated requests
      localStorage.setItem('auth_token', result.data.token);
      return result.data.user;
    } else {
      throw new Error(result.message || 'Registration failed');
    }
  } catch (error) {
    console.error('Registration error:', error);
    throw error;
  }
}

// Register Admin
async function registerAdmin(adminData) {
  try {
    const response = await fetch('http://your-api-url/api/register', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        name: adminData.name,
        email: adminData.email,
        password: adminData.password,
        password_confirmation: adminData.passwordConfirmation,
        role: 'admin'
      })
    });

    const result = await response.json();
    
    if (result.status === 201) {
      localStorage.setItem('auth_token', result.data.token);
      return result.data.user;
    } else {
      throw new Error(result.message || 'Registration failed');
    }
  } catch (error) {
    console.error('Registration error:', error);
    throw error;
  }
}
```

### Vue.js Example

```vue
<template>
  <form @submit.prevent="register">
    <!-- Regular User Fields -->
    <div v-if="userType === 'user'">
      <input v-model="form.first_name" placeholder="First Name" required />
      <input v-model="form.last_name" placeholder="Last Name" required />
      <input v-model="form.whatsapp_number" placeholder="WhatsApp Number" required />
      <input v-model="form.city" placeholder="City" required />
      <input v-model="form.state" placeholder="State" required />
      <input v-model="form.country" placeholder="Country" required />
      <input v-model="form.profession" placeholder="Profession" required />
      <select v-model="form.gender" required>
        <option value="male">Male</option>
        <option value="female">Female</option>
        <option value="other">Other</option>
        <option value="prefer_not_to_say">Prefer not to say</option>
      </select>
      <input v-model.number="form.age" type="number" placeholder="Age" required />
      <input v-model="form.educational_qualification" placeholder="Educational Qualification" required />
    </div>
    
    <!-- Admin Fields -->
    <div v-else>
      <input v-model="form.name" placeholder="Name" required />
    </div>
    
    <!-- Common Fields -->
    <input v-model="form.email" type="email" placeholder="Email" required />
    <input v-model="form.password" type="password" placeholder="Password" required />
    <input v-model="form.password_confirmation" type="password" placeholder="Confirm Password" required />
    
    <button type="submit">Register</button>
  </form>
</template>

<script>
export default {
  data() {
    return {
      userType: 'user', // or 'admin'
      form: {
        first_name: '',
        last_name: '',
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        whatsapp_number: '',
        city: '',
        state: '',
        country: '',
        profession: '',
        gender: '',
        age: null,
        educational_qualification: '',
        role: 'user'
      }
    };
  },
  methods: {
    async register() {
      try {
        const payload = { ...this.form };
        payload.role = this.userType;
        
        const response = await fetch('http://your-api-url/api/register', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        
        if (result.status === 201) {
          localStorage.setItem('auth_token', result.data.token);
          this.$router.push('/dashboard');
        } else {
          alert('Registration failed: ' + JSON.stringify(result.errors));
        }
      } catch (error) {
        console.error('Registration error:', error);
      }
    }
  }
};
</script>
```

---

## Database Schema

### User Table Fields

**Common Fields:**
- `id` (primary key)
- `name` (string) - Full name for admin, auto-generated from first_name + last_name for users
- `email` (string, unique)
- `password` (hashed)
- `role` (enum: `admin`, `user`)
- `created_at`, `updated_at`

**Regular User Specific Fields:**
- `first_name` (string, nullable)
- `last_name` (string, nullable)
- `whatsapp_number` (string, nullable)
- `city` (string, nullable)
- `state` (string, nullable)
- `country` (string, nullable)
- `profession` (string, nullable)
- `gender` (enum, nullable)
- `age` (integer, nullable)
- `educational_qualification` (string, nullable)
- `contact` (string, nullable) - Legacy field

---

## Notes

1. **Backward Compatibility**: The `name` field is automatically populated for regular users by combining `first_name` and `last_name` for backward compatibility.

2. **Role Default**: If `role` is not provided, it defaults to `user`.

3. **Token**: Upon successful registration, an authentication token is returned that should be used for subsequent authenticated requests.

4. **Password Confirmation**: The `password_confirmation` field must match the `password` field.

5. **Email Uniqueness**: Each email can only be registered once in the system.

---

## Migration

To apply the database changes, run:

```bash
php artisan migrate
```

This will add the new fields to the users table:
- `first_name`
- `last_name`
- `whatsapp_number`
- `city`
- `state`
- `country`
- `profession`
- `educational_qualification`

