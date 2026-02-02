# REST API Project with JWT Authentication

This is a REST API built with CodeIgniter 4, featuring JWT authentication, role-based access control, and integration with Midtrans payment gateway. I built this as a learning project to understand how authentication and authorization work in real-world applications.

## What's Inside?

The API includes several key features that you'd typically find in an e-commerce backend:

- User authentication using JWT tokens
- Role-based authorization (Admin & User roles)
- Product management
- Order processing
- Payment integration with Midtrans
- Request rate limiting
- Redis for caching

## Getting Started

### Prerequisites

Make sure you have these installed on your machine:
- PHP 8.1 or higher
- Composer
- MySQL
- Redis (optional, but recommended for caching)

### Installation

1. Clone the repository
```bash
git clone <your-repo-url>
cd <project-folder>
```

2. Install dependencies
```bash
composer install
```

3. Configure your environment
```bash
cp env .env
```

Edit the `.env` file with your database credentials and other settings:
```
database.default.hostname = localhost
database.default.database = your_database
database.default.username = your_username
database.default.password = your_password

jwt.key = your-secret-key-here
```

4. Run migrations and seeders
```bash
php spark migrate
php spark db:seed DatabaseSeeder
```

5. Start the development server
```bash
php spark serve
```

The API will be available at `http://localhost:8080`

## Default Test Accounts

After running the seeder, you can use these accounts for testing:

| Role  | Email                | Password   |
|-------|---------------------|------------|
| Admin | admin@example.com   | admin123   |
| User  | user@example.com    | user123    |
| User  | john@example.com    | password123|

## How Authentication Works

The authentication flow is pretty straightforward:

1. User registers or logs in
2. Server returns a JWT token
3. Client includes the token in subsequent requests via the `Authorization` header
4. Server validates the token and processes the request

Token format in requests:
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

Tokens expire after 1 hour by default. You can refresh them using the `/api/auth/refresh` endpoint.

## Authorization Levels

There are two user roles in the system:

### Admin
- Full CRUD access to all resources
- Can manage products
- Can view all orders and users
- Can update order statuses

### User
- Can view products
- Can create and manage their own orders
- Can view their own payment history
- Cannot access other users' data

## API Endpoints

Here's a quick overview of available endpoints. For detailed request/response examples, check the documentation file.

### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login
- `GET /api/auth/me` - Get current user info
- `POST /api/auth/refresh` - Refresh token
- `POST /api/auth/logout` - Logout

### User Management
- `GET /api/users/profile` - Get own profile
- `PUT /api/users/profile` - Update profile
- `PUT /api/users/password` - Change password
- `GET /api/users` - Get all users (admin only)

### Products
- `GET /api/products` - List products (with pagination)
- `GET /api/products/{id}` - Get product details
- `POST /api/products` - Create product (admin only)
- `PUT /api/products/{id}` - Update product (admin only)
- `DELETE /api/products/{id}` - Delete product (admin only)

### Orders
- `POST /api/orders` - Create order
- `GET /api/orders` - Get orders (filtered by user)
- `GET /api/orders/{id}` - Get order details
- `PUT /api/orders/{id}/cancel` - Cancel order
- `PUT /api/orders/{id}/status` - Update status (admin only)

### Payments
- `POST /api/payments/create` - Create payment transaction
- `GET /api/payments/status/{order_number}` - Check payment status
- `GET /api/payments/history` - View payment history

## Quick Test

Want to quickly test if everything works? Try this:

```bash
# Login as admin
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "admin123"
  }'
```

Copy the token from the response, then try accessing a protected endpoint:

```bash
# Get products (replace YOUR_TOKEN with the actual token)
curl -X GET http://localhost:8080/api/products \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Common Issues

### "Token not provided" error
Make sure you're including the `Authorization` header with `Bearer` prefix in your requests.

### "Invalid or expired token" error
Your token might have expired (1 hour lifetime). Login again to get a new token, or use the refresh endpoint.

### "Insufficient permissions" error
You're trying to access an admin-only endpoint with a regular user account. Use the admin credentials instead.

### Database connection errors
Double-check your `.env` file settings, especially the database credentials.

## Testing with Postman

If you prefer using Postman for testing:

1. Create a new environment
2. Add variable `base_url` with value `http://localhost:8080/api`
3. After logging in, save the token in an environment variable
4. Use `{{base_url}}` and `{{token}}` in your requests

## Project Structure

```
app/
├── Config/         # Configuration files
├── Controllers/    # API controllers
├── Models/         # Database models
├── Filters/        # Auth & CORS filters
└── Libraries/      # Custom libraries (JWT, Midtrans)

writable/
└── logs/          # Application logs
```

## Notes

- All timestamps use `Asia/Jakarta` timezone
- Rate limit is set to 60 requests per minute per IP
- Max file upload size is 2MB
- Pagination is limited to 100 items per request

## What I Learned

Building this project helped me understand:
- How JWT tokens work in real applications
- Implementing role-based access control
- Working with payment gateways
- Proper error handling and validation
- API security best practices

## Future Improvements

Some features I'm planning to add:
- Email verification for new users
- Password reset functionality
- File upload for product images
- Admin dashboard
- More payment methods

## Contributing

Feel free to fork this project and submit pull requests. I'm always open to suggestions and improvements!

## License

This project is open source and available under the [MIT License](LICENSE).
