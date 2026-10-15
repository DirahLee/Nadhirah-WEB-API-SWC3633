# Ticketing API

A PHP and MySQL REST API for an event ticketing system, with three web portals: **admin**, **organiser** and **customer**. Customers browse events and book tickets, organisers publish events and manage venues, and admins manage everything.

## Features

- REST API for users, venues, events and bookings (GET, POST, PUT, DELETE)
- Login with hashed passwords (`password_hash` / `password_verify`)
- Three role-based portals: admin, organiser and customer
- Ticket booking inside a database transaction, so ticket counts stay correct
- Customer cancellation restores the tickets to the event
- Live currency conversion of ticket prices using the external API [open.er-api.com](https://open.er-api.com)
- Prepared statements (PDO) on every query to prevent SQL injection
- Request logging and rate limiting (60 requests per minute per IP) in `config/middleware.php`

## Tech stack

PHP 8, MySQL (PDO), HTML, CSS, JavaScript (Fetch API), XAMPP, Postman for testing

## Project structure

```
ticketing-api/
├── config/
│   ├── database.php      # PDO database connection + CORS headers
│   └── middleware.php    # CORS, request logging, rate limiting
├── staff-login.html      # Login page for admin and organiser
├── index.html            # Admin dashboard
├── organiser.html        # Organiser portal
├── customer.html         # Customer portal (login, register, book, cancel)
├── login.php             # POST: log in
├── users.php             # Users CRUD
├── venues.php            # Venues CRUD
├── events.php            # Events CRUD + currency conversion
├── bookings.php          # Bookings CRUD (admin)
├── customer.php          # Customer events, bookings and cancellation
└── database.sql          # Database tables
```

## Setup

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL**.
2. Copy this project to `C:\xampp\htdocs\ticketing-api`.
3. Open phpMyAdmin and run the SQL in the [Database](#database) section to create `ticketing_db`.
4. Open `config/database.php` and check the connection settings. The default is:
   ```php
   private $host = "localhost:3307";
   private $db_name = "ticketing_db";
   private $username = "root";
   private $password = "";
   ```
   Change the port if your MySQL does not run on 3307.
5. Create an admin account (see [Demo accounts](#demo-accounts)).
6. Open the portals in your browser:

| Portal | URL |
|--------|-----|
| Admin / organiser login | `http://localhost/ticketing-api/staff-login.html` |
| Customer | `http://localhost/ticketing-api/customer.html` |

## Database

```sql
CREATE DATABASE IF NOT EXISTS ticketing_db;
USE ticketing_db;

CREATE TABLE Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Venues (
    venue_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    capacity INT NOT NULL
);

CREATE TABLE Events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATETIME NOT NULL,
    venue_id INT NOT NULL,
    organiser_id INT NOT NULL,
    ticket_price DECIMAL(10, 2) NOT NULL,
    available_tickets INT NOT NULL,
    FOREIGN KEY (venue_id) REFERENCES Venues(venue_id) ON DELETE CASCADE,
    FOREIGN KEY (organiser_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

CREATE TABLE Bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    event_id INT NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    status VARCHAR(50) DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES Users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES Events(event_id) ON DELETE CASCADE
);
```

## Demo accounts

Passwords are stored as bcrypt hashes, so they cannot be inserted as plain text. This creates an admin and an organiser for testing. Both use the password `123456`.

```sql
INSERT INTO Users (name, email, password, role) VALUES
('Admin', 'admin@ticketing.com', '$2y$10$.QH8QrXZaNrIZa8bDmJhXO5YONkxXJCqX4VRNf47nZHW8WwGRAVvS', 'admin'),
('Organiser', 'organiser@ticketing.com', '$2y$10$.QH8QrXZaNrIZa8bDmJhXO5YONkxXJCqX4VRNf47nZHW8WwGRAVvS', 'organiser');
```

Customers can register themselves on `customer.html`. These demo accounts are for local testing only. Change the passwords before using the project anywhere public.

## API endpoints

Base URL: `http://localhost/ticketing-api/`

All requests and responses use JSON. A successful response looks like `{ "status": "success", "data": ... }`.

### Authentication

| Method | Endpoint | Body | Description |
|--------|----------|------|-------------|
| POST | `login.php` | `email`, `password` | Log in and get the user's id, name, email and role |

### Users

| Method | Endpoint | Body or query | Description |
|--------|----------|---------------|-------------|
| GET | `users.php` | optional `?id=1` | List users or get one (passwords are never returned) |
| POST | `users.php` | `name`, `email`, `password`, `role` | Create a user (password is hashed) |
| PUT | `users.php` | `user_id`, `name`, `role` | Update a user |
| DELETE | `users.php?id=1` | | Delete a user |

### Venues

| Method | Endpoint | Body or query | Description |
|--------|----------|---------------|-------------|
| GET | `venues.php` | optional `?id=1` | List venues or get one |
| POST | `venues.php` | `name`, `location`, `capacity` | Create a venue |
| PUT | `venues.php` | `venue_id`, `name`, `capacity` | Update a venue |
| DELETE | `venues.php?id=1` | | Delete a venue |

### Events

| Method | Endpoint | Body or query | Description |
|--------|----------|---------------|-------------|
| GET | `events.php` | optional `?id=1&currency=USD` | List events or get one. With `currency`, the response adds `ticket_price_converted` |
| POST | `events.php` | `title`, `description`, `event_date`, `venue_id`, `organiser_id`, `ticket_price`, `available_tickets` | Create an event |
| PUT | `events.php` | `event_id`, `title`, `ticket_price`, `available_tickets` | Update an event |
| DELETE | `events.php?id=1` | | Delete an event |

### Bookings (admin)

| Method | Endpoint | Body or query | Description |
|--------|----------|---------------|-------------|
| GET | `bookings.php` | optional `?id=1` | List bookings with customer and event names |
| POST | `bookings.php` | `customer_id`, `event_id`, `quantity` | Create a booking and reduce available tickets |
| PUT | `bookings.php` | `booking_id`, `status` | Change a booking status |
| DELETE | `bookings.php?id=1` | | Delete a booking |

### Customer

| Method | Endpoint | Body or query | Description |
|--------|----------|---------------|-------------|
| GET | `customer.php` | none | List all events with venue details |
| GET | `customer.php?event_id=1` | | Get one event |
| GET | `customer.php?customer_id=1` | | List a customer's bookings |
| POST | `customer.php` | `customer_id`, `event_id`, `quantity` | Book tickets (customer accounts only) |
| DELETE | `customer.php?booking_id=1` | | Cancel a booking and restore the tickets |

### Example request

`POST /ticketing-api/customer.php`

```json
{
  "customer_id": 3,
  "event_id": 1,
  "quantity": 2
}
```

Response:

```json
{
  "status": "success",
  "message": "Booking successful.",
  "data": { "booking_id": "5", "total_price": 120, "quantity": 2 }
}
```

## Roles

| Role | Page | What they can do |
|------|------|------------------|
| Admin | `index.html` | Manage events, venues, users and bookings |
| Organiser | `organiser.html` | Create events and manage venues |
| Customer | `customer.html` | Register, browse events, book and cancel tickets |

## Security notes

- Passwords are hashed with bcrypt and never returned by the API.
- All database queries use prepared statements.
- The portal pages check the logged-in user in the browser. The API itself does not check sessions or tokens, so this is suitable for a course project or local use. For real use, add session or token checks in PHP.
- `api_requests.log` is created by the logging middleware. Add it to `.gitignore`.

## Author

[Your name], [your course / university]
