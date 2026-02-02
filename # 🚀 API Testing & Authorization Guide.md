# 🚀 API Testing & Authorization Guide

Panduan lengkap untuk testing REST API dengan semua fitur authentication dan authorization.

---

## 📋 Table of Contents

1. [Quick Start](#quick-start)
2. [Authentication Flow](#authentication-flow)
3. [Authorization Roles](#authorization-roles)
4. [API Endpoints](#api-endpoints)
5. [Testing Examples](#testing-examples)
6. [Common Errors](#common-errors)

---

## 🎯 Quick Start

### **1. Start Server**
```bash
php spark serve
```

Server akan jalan di: `http://localhost:8080`

### **2. Default Credentials**

Setelah migration & seeding:

| Role  | Email                | Password   |
|-------|---------------------|------------|
| Admin | admin@example.com   | admin123   |
| User  | user@example.com    | user123    |
| User  | john@example.com    | password123|

### **3. Test Quick**
```bash
# Login
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "admin123"
  }'
```

Copy `token` dari response untuk request selanjutnya.

---

## 🔐 Authentication Flow

### **Step-by-Step Authentication**
```
┌─────────────┐
│ 1. Register │ atau Login
└──────┬──────┘
       │
       ▼
┌─────────────┐
│ 2. Get Token│ (JWT)
└──────┬──────┘
       │
       ▼
┌─────────────┐
│ 3. Use Token│ di setiap request
└──────┬──────┘
       │
       ▼
┌─────────────┐
│ 4. Access   │ Protected Endpoints
│   Resources │
└─────────────┘
```

### **Token Format**

Setiap request ke protected endpoint harus include:
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

### **Token Expiration**

- Default: **3600 seconds (1 hour)**
- Configurable di `.env`: `jwt.expire = 3600`
- Gunakan `/api/auth/refresh` untuk refresh token sebelum expired

---

## 👥 Authorization Roles

### **Role Hierarchy**
```
┌─────────┐
│  ADMIN  │ ← Full access (CRUD semua resource)
└────┬────┘
     │
     ▼
┌─────────┐
│  USER   │ ← Limited access (CRUD own resources)
└─────────┘
```

### **Permission Matrix**

| Endpoint                    | Admin | User | Public |
|----------------------------|-------|------|--------|
| POST /auth/register        | ✅    | ✅   | ✅     |
| POST /auth/login           | ✅    | ✅   | ✅     |
| GET /auth/me               | ✅    | ✅   | ❌     |
| GET /users                 | ✅    | ❌   | ❌     |
| GET /users/profile         | ✅    | ✅   | ❌     |
| PUT /users/profile         | ✅    | ✅   | ❌     |
| DELETE /users/{id}         | ✅    | ❌   | ❌     |
| GET /products              | ✅    | ✅   | ❌     |
| POST /products             | ✅    | ❌   | ❌     |
| PUT /products/{id}         | ✅    | ❌   | ❌     |
| DELETE /products/{id}      | ✅    | ❌   | ❌     |
| GET /orders                | ✅    | ✅*  | ❌     |
| POST /orders               | ✅    | ✅   | ❌     |
| PUT /orders/{id}/cancel    | ✅    | ✅*  | ❌     |
| PUT /orders/{id}/status    | ✅    | ❌   | ❌     |
| POST /payments/create      | ✅    | ✅   | ❌     |
| GET /payments/history      | ✅    | ✅*  | ❌     |

**\*** User hanya bisa akses data milik sendiri

---

## 🌐 API Endpoints

### **Base URL**
```
http://localhost:8080/api
```

---

## 1️⃣ AUTHENTICATION

### **Register**
```bash
POST /auth/register
Content-Type: application/json

{
  "username": "johndoe",
  "email": "john@example.com",
  "password": "password123",
  "full_name": "John Doe",
  "phone": "081234567890"
}
```

**Response:**
```json
{
  "status": true,
  "message": "User registered successfully",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "id": 4,
      "username": "johndoe",
      "email": "john@example.com",
      "role": "user"
    }
  }
}
```

---

### **Login**
```bash
POST /auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "admin123"
}
```

**Response:**
```json
{
  "status": true,
  "message": "Login successful",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "id": 1,
      "username": "admin",
      "email": "admin@example.com",
      "role": "admin"
    }
  }
}
```

---

### **Get Current User**
```bash
GET /auth/me
Authorization: Bearer {token}
```

**Response:**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "username": "admin",
    "email": "admin@example.com",
    "full_name": "Administrator",
    "role": "admin"
  }
}
```

---

### **Refresh Token**
```bash
POST /auth/refresh
Authorization: Bearer {token}
```

**Response:**
```json
{
  "status": true,
  "message": "Token refreshed successfully",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

---

### **Logout**
```bash
POST /auth/logout
Authorization: Bearer {token}
```

**Response:**
```json
{
  "status": true,
  "message": "Logout successful"
}
```

---

## 2️⃣ USERS

### **Get Profile (Current User)**
```bash
GET /users/profile
Authorization: Bearer {token}
```

---

### **Update Profile**
```bash
PUT /users/profile
Authorization: Bearer {token}
Content-Type: application/json

{
  "full_name": "John Doe Updated",
  "phone": "081999888777"
}
```

---

### **Change Password**
```bash
PUT /users/password
Authorization: Bearer {token}
Content-Type: application/json

{
  "current_password": "admin123",
  "new_password": "newpassword123",
  "confirm_password": "newpassword123"
}
```

---

### **Get All Users (Admin Only)**
```bash
GET /users?page=1&limit=10
Authorization: Bearer {admin_token}
```

---

## 3️⃣ PRODUCTS

### **Get All Products**
```bash
GET /products?page=1&limit=10&search=laptop&category=electronics
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 10, max: 100)
- `search` (optional): Search keyword
- `category` (optional): Filter by category

---

### **Get Product Detail**
```bash
GET /products/{id}
Authorization: Bearer {token}
```

---

### **Create Product (Admin Only)**
```bash
POST /products
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "name": "MacBook Pro M3",
  "description": "Latest MacBook Pro with M3 chip",
  "price": 25000000,
  "stock": 10,
  "category": "electronics"
}
```

---

### **Update Product (Admin Only)**
```bash
PUT /products/{id}
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "price": 23000000,
  "stock": 15
}
```

---

### **Delete Product (Admin Only)**
```bash
DELETE /products/{id}
Authorization: Bearer {admin_token}
```

---

## 4️⃣ ORDERS

### **Create Order**
```bash
POST /orders
Authorization: Bearer {token}
Content-Type: application/json

{
  "order_items": [
    {
      "product_id": 1,
      "quantity": 2
    },
    {
      "product_id": 4,
      "quantity": 1
    }
  ],
  "payment_method": "manual",
  "notes": "Kirim pagi hari"
}
```

**Response:**
```json
{
  "status": true,
  "message": "Order created successfully",
  "data": {
    "id": 1,
    "order_number": "ORD-20241231-ABC12345",
    "user_id": 1,
    "total_amount": "35000000.00",
    "status": "pending",
    "payment_status": "pending",
    "items": [
      {
        "product_id": 1,
        "product_name": "Laptop ASUS ROG",
        "quantity": 2,
        "subtotal": "30000000.00"
      },
      {
        "product_id": 4,
        "product_name": "Sony WH-1000XM5",
        "quantity": 1,
        "subtotal": "5000000.00"
      }
    ]
  }
}
```

---

### **Get My Orders**
```bash
GET /orders?page=1&limit=10
Authorization: Bearer {token}
```

---

### **Get Order Detail**
```bash
GET /orders/{id}
Authorization: Bearer {token}
```

---

### **Cancel Order**
```bash
PUT /orders/{id}/cancel
Authorization: Bearer {token}
```

**Note:** Hanya bisa cancel order dengan status `pending` atau `processing`

---

### **Update Order Status (Admin Only)**
```bash
PUT /orders/{id}/status
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "status": "processing"
}
```

**Valid Status Transitions:**
- `pending` → `processing`, `cancelled`
- `processing` → `shipped`, `cancelled`
- `shipped` → `delivered`, `cancelled`
- `delivered` → (final state)
- `cancelled` → (final state)

---

## 5️⃣ PAYMENTS (Midtrans)

### **Create Payment Transaction**
```bash
POST /payments/create
Authorization: Bearer {token}
Content-Type: application/json

{
  "order_items": [
    {
      "product_id": 1,
      "quantity": 1
    }
  ],
  "payment_method": "credit_card",
  "notes": "Test payment"
}
```

**Response:**
```json
{
  "status": true,
  "message": "Transaction created successfully",
  "data": {
    "order_id": 1,
    "order_number": "ORD-20241231-XYZ98765",
    "amount": 15000000,
    "snap_token": "66e4fa55-fdac-4ef9-91b5-733b97d1b862",
    "payment_url": "https://app.sandbox.midtrans.com/snap/v2/vtweb/..."
  }
}
```

---

### **Check Payment Status**
```bash
GET /payments/status/{order_number}
Authorization: Bearer {token}
```

---

### **Payment History**
```bash
GET /payments/history?page=1&limit=10
Authorization: Bearer {token}
```

---

## 🧪 Testing Examples

### **Example 1: Complete User Flow**
```bash
# 1. Register
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser",
    "email": "test@example.com",
    "password": "password123",
    "full_name": "Test User"
  }'

# 2. Login (Copy token from response)
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123"
  }'

# 3. Get Products (Replace YOUR_TOKEN)
curl -X GET http://localhost:8080/api/products \
  -H "Authorization: Bearer YOUR_TOKEN"

# 4. Create Order
curl -X POST http://localhost:8080/api/orders \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "order_items": [{"product_id": 1, "quantity": 1}],
    "payment_method": "manual"
  }'

# 5. Get My Orders
curl -X GET http://localhost:8080/api/orders \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### **Example 2: Admin Operations**
```bash
# 1. Login as Admin
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "admin123"
  }'

# 2. Create Product (Admin)
curl -X POST http://localhost:8080/api/products \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "New Product",
    "price": 100000,
    "stock": 50,
    "category": "electronics"
  }'

# 3. Get All Users (Admin)
curl -X GET http://localhost:8080/api/users \
  -H "Authorization: Bearer ADMIN_TOKEN"

# 4. Update Order Status (Admin)
curl -X PUT http://localhost:8080/api/orders/1/status \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "processing"
  }'
```

---

### **Example 3: Testing Authorization**
```bash
# 1. Login as Regular User
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "user123"
  }'

# 2. Try to Create Product (Should FAIL - 403 Forbidden)
curl -X POST http://localhost:8080/api/products \
  -H "Authorization: Bearer USER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Product",
    "price": 100000,
    "stock": 10
  }'

# Expected Response:
# {
#   "status": false,
#   "message": "Only administrators can create products"
# }

# 3. Try to Get All Users (Should FAIL - 403 Forbidden)
curl -X GET http://localhost:8080/api/users \
  -H "Authorization: Bearer USER_TOKEN"

# Expected Response:
# {
#   "status": false,
#   "message": "Insufficient permissions. Required role: admin"
# }
```

---

## ❌ Common Errors

### **1. Missing Token (401)**
```json
{
  "status": false,
  "message": "Token not provided"
}
```

**Solution:** Add `Authorization: Bearer {token}` header

---

### **2. Invalid Token (401)**
```json
{
  "status": false,
  "message": "Invalid or expired token"
}
```

**Solution:** 
- Check if token is correct
- Token might be expired, login again
- Use `/auth/refresh` to get new token

---

### **3. Insufficient Permissions (403)**
```json
{
  "status": false,
  "message": "Only administrators can create products"
}
```

**Solution:** Login with admin account

---

### **4. Validation Error (400)**
```json
{
  "status": false,
  "message": "Validation errors",
  "errors": {
    "email": "Email already exists",
    "password": "Password must be at least 6 characters"
  }
}
```

**Solution:** Fix the validation errors

---

### **5. Resource Not Found (404)**
```json
{
  "status": false,
  "message": "Product not found"
}
```

**Solution:** Check if resource ID exists

---

### **6. Insufficient Stock (400)**
```json
{
  "status": false,
  "message": "Insufficient stock for Laptop ASUS ROG. Available: 5"
}
```

**Solution:** Reduce quantity or wait for restock

---

## 🎨 Using Postman

### **Setup Postman**

1. **Import Collection**
   - File → Import
   - Select `Postman_Collection.json`

2. **Create Environment**
   - Click Environments → Create
   - Add variables:
```
     base_url: http://localhost:8080/api
     admin_token: (will be set automatically)
     user_token: (will be set automatically)
```

3. **Auto-save Tokens**
   - Login requests sudah di-set untuk auto-save token
   - Token otomatis digunakan di request berikutnya

### **Testing Flow in Postman**

1. **Auth** → **Login Admin** (token auto-saved)
2. **Products** → **Get All Products**
3. **Products** → **Create Product** (uses admin_token)
4. **Auth** → **Login User** (save as user_token)
5. **Orders** → **Create Order** (uses user_token)
6. **Try**: User → **Create Product** (should fail)

---

## 📊 Testing Checklist
```
□ Authentication
  □ Register new user
  □ Login admin
  □ Login user
  □ Get current user (me)
  □ Refresh token
  □ Logout

□ Authorization
  □ User can access own resources
  □ User cannot access admin endpoints
  □ User cannot access other user's data
  □ Admin can access all resources

□ Products
  □ Get all products
  □ Search products
  □ Filter by category
  □ Get product detail
  □ Create product (admin)
  □ Update product (admin)
  □ Delete product (admin)

□ Orders
  □ Create order
  □ Get my orders
  □ Get order detail
  □ Cancel order
  □ Update status (admin)

□ Payments
  □ Create transaction
  □ Check payment status
  □ View payment history

□ Error Handling
  □ Missing token
  □ Invalid token
  □ Insufficient permissions
  □ Validation errors
  □ Resource not found
  □ Business logic errors
```

---

## 🆘 Need Help?

- Check logs: `tail -f writable/logs/log-*.log`
- Enable debug mode in `.env`: `CI_ENVIRONMENT = development`
- Check database: `mysql -u root -p ci4_restapi`
- Check Redis: `redis-cli ping`

---

## 📝 Notes

- All timestamps are in `Asia/Jakarta` timezone
- Token expires in 1 hour (3600 seconds)
- Rate limit: 60 requests per minute per IP
- Maximum file upload: 2MB
- Pagination max limit: 100 items

---

