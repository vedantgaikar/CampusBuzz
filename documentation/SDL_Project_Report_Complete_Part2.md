# SDL Event Management System: Comprehensive Project Report - Part 2

## 3. System Design

### System Architecture

The SDL Event Management System follows a three-tier architecture pattern, separating the application into three logical components:

1. **Presentation Layer (Frontend)**

   - HTML5, CSS3, and JavaScript for the user interface
   - Bootstrap framework for responsive design
   - Client-side validation and interaction handling

2. **Application Layer (Backend)**

   - PHP 7.4+ business logic implementation
   - MVC-inspired organization of code
   - Session management and authentication services
   - Form processing and validation
   - Database interaction through PDO

3. **Data Layer**
   - MySQL database for persistent storage
   - Optimized tables and relationships
   - Transaction management for data integrity

### Component Design

#### Authentication System

The authentication system manages user registration, login, and session handling:

- **Session-based authentication**: Uses PHP sessions to maintain user state
- **Password security**: Implements password hashing using PHP's `password_hash()` function
- **Role-based access control**: Differentiates between student and organizer permissions
- **Session timeout**: Automatically logs out inactive users after a specified period

#### User Management

The user management component handles user profiles and preferences:

- **Profile editor**: Interface for users to update personal information
- **Interest management**: System for selecting and updating interest categories
- **Account settings**: Options for email notifications and account management
- **Profile pictures**: Support for user avatar uploads and management

#### Event Management

The event management component provides tools for creating and managing events:

- **Event creation wizard**: Step-by-step form for creating events
- **Media management**: Support for uploading and managing event images
- **Scheduling tools**: Date and time selection with validation
- **Status control**: Options to publish, unpublish, or cancel events
- **Category management**: Tagging events with relevant categories
- **Location management**: Specifying physical or virtual event locations

#### Registration System

The registration system handles event sign-ups and attendee management:

- **One-click registration**: Streamlined process for students to register
- **Capacity management**: Automatic enforcement of event capacity limits
- **Waitlist functionality**: Option for students to join waitlists for full events
- **Registration status**: Tracking of pending, confirmed, and cancelled registrations
- **Attendance tracking**: Basic tools for recording attendance

#### Recommendation Engine

The recommendation system provides personalized event suggestions:

- **Interest-based matching**: Correlating user interests with event categories
- **Recency weighting**: Prioritizing upcoming events in recommendations
- **Popularity signals**: Incorporating registration trends into recommendations
- **Category diversity**: Ensuring a mix of event types in recommendations

#### Notification System

The notification component keeps users informed about relevant events:

- **System notifications**: Alerts about event changes and updates
- **Registration confirmations**: Notifications upon successful registration
- **Reminders**: Automated reminders for upcoming registered events
- **Status updates**: Notifications about event cancellations or changes

#### Dashboard Interfaces

The dashboard components provide personalized views for different user roles:

- **Student dashboard**: Displays registered events, recommendations, and notifications
- **Organizer dashboard**: Shows created events, attendee statistics, and management tools
- **Administrative widgets**: Quick-access cards for common actions
- **Status summaries**: Visual indicators of event and registration status

### Technology Stack

#### Frontend Technologies

- **HTML5**: Semantic markup for page structure
- **CSS3**: Styling and layout with modern CSS features
- **JavaScript**: Client-side interactivity and validation
- **Bootstrap 4**: Responsive design framework
- **Font Awesome**: Icon library for visual elements

#### Backend Technologies

- **PHP 7.4+**: Server-side scripting language
- **PDO (PHP Data Objects)**: Database abstraction layer
- **Session Management**: PHP's built-in session handling

#### Database Technologies

- **MySQL 5.7+**: Relational database management system
- **InnoDB Engine**: Transaction-safe storage engine
- **Foreign Key Constraints**: Referential integrity enforcement

#### Development Tools

- **Git**: Version control system
- **Visual Studio Code**: Primary code editor
- **XAMPP/WAMP**: Local development environment
- **phpMyAdmin**: Database administration tool

### Design Patterns and Principles

The system incorporates several design patterns and principles:

1. **MVC-inspired Architecture**: Separation of data models, view templates, and controller logic
2. **Singleton Pattern**: Used for database connection management
3. **Factory Pattern**: Implemented for creating complex objects
4. **Repository Pattern**: Used for data access abstraction
5. **SOLID Principles**: Applied where appropriate for maintainable code
6. **DRY (Don't Repeat Yourself)**: Code reuse through includes and functions
7. **Progressive Enhancement**: Core functionality works without JavaScript

### Security Considerations

The system implements several security measures:

1. **Input Validation**: All user inputs are validated server-side
2. **Prepared Statements**: Used for all database queries to prevent SQL injection
3. **CSRF Protection**: Token-based protection for forms
4. **XSS Prevention**: Output escaping for user-generated content
5. **Password Hashing**: Secure storage of user credentials
6. **Session Security**: HTTPOnly cookies and session regeneration
7. **Access Control**: Proper permission checking before actions

### Performance Optimization

Performance considerations in the design include:

1. **Database Indexing**: Strategic indexes on frequently queried columns
2. **Query Optimization**: Efficient SQL queries with proper JOINs
3. **Pagination**: Limiting result sets for large data collections
4. **Caching**: Strategic caching of frequently accessed data
5. **Lazy Loading**: Loading resources only when needed
6. **Asset Optimization**: Minification of CSS and JavaScript files
7. **Image Optimization**: Proper sizing and compression of uploaded images

### Accessibility Considerations

The system is designed with accessibility in mind:

1. **Semantic HTML**: Proper use of HTML elements for their intended purpose
2. **ARIA Attributes**: Used where appropriate to enhance accessibility
3. **Keyboard Navigation**: Full functionality available via keyboard
4. **Color Contrast**: Sufficient contrast ratios for text visibility
5. **Screen Reader Support**: Alternative text for images and meaningful labels
6. **Responsive Design**: Adaptation to different screen sizes and orientations

### Scalability Considerations

The architecture supports future scaling through:

1. **Modular Design**: Components can be extended or replaced independently
2. **Database Scalability**: Table design supports growth in users and events
3. **Code Organization**: Logical separation of concerns facilitates maintenance
4. **Configuration Management**: Environment-specific settings for different deployments
5. **API-Ready Structure**: Core functionality organized to potentially expose APIs

## 4. Database Design

### Database Model Overview

The SDL Event Management System uses a relational database model implemented in MySQL. The database schema is designed to efficiently store and retrieve information about users, events, registrations, categories, and other related entities.

### Database Tables

#### Users Table

The `users` table stores core user account information:

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('student', 'organizer', 'admin') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);
```

Key attributes:

- `id`: Unique identifier for each user
- `name`: User's full name
- `email`: User's email address (used for login)
- `password`: Hashed password
- `user_type`: Role-based access control identifier
- `last_login`: Timestamp of the user's most recent login

#### User_Profiles Table

The `user_profiles` table extends user information with optional profile details:

```sql
CREATE TABLE user_profiles (
    user_id INT PRIMARY KEY,
    phone VARCHAR(20) NULL,
    institution VARCHAR(100) NULL,
    bio TEXT NULL,
    profile_picture VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

Key attributes:

- `user_id`: Foreign key linking to the users table
- `phone`: User's contact phone number
- `institution`: School or organization affiliation
- `bio`: Brief user description
- `profile_picture`: Path to stored profile image

#### Categories Table

The `categories` table stores event categories for organization and filtering:

```sql
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

Key attributes:

- `id`: Unique identifier for each category
- `name`: Category name (e.g., "Workshop", "Seminar", "Competition")
- `description`: Optional description of the category

#### User_Interests Table

The `user_interests` table creates a many-to-many relationship between users and categories:

```sql
CREATE TABLE user_interests (
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (user_id, category_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);
```

Key attributes:

- `user_id`: Foreign key linking to the users table
- `category_id`: Foreign key linking to the categories table
- Combined primary key ensures unique user-category pairs

#### Events Table

The `events` table stores information about all events in the system:

```sql
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    event_date DATETIME NOT NULL,
    end_date DATETIME NULL,
    organizer_id INT NOT NULL,
    location VARCHAR(255) NOT NULL,
    capacity INT NULL,
    image VARCHAR(255) NULL,
    status ENUM('draft', 'published', 'cancelled', 'completed') NOT NULL DEFAULT 'draft',
    is_featured BOOLEAN DEFAULT 0,
    registration_deadline DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE
);
```

Key attributes:

- `id`: Unique identifier for each event
- `name`: Event title
- `description`: Detailed event description
- `event_date`: Start date and time
- `end_date`: Optional end date and time
- `organizer_id`: Foreign key linking to the creator user
- `location`: Physical or virtual location
- `capacity`: Maximum number of attendees (NULL means unlimited)
- `status`: Current publication/availability status
- `is_featured`: Flag for featuring events in recommendations

#### Event_Categories Table

The `event_categories` table creates a many-to-many relationship between events and categories:

```sql
CREATE TABLE event_categories (
    event_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (event_id, category_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);
```

Key attributes:

- `event_id`: Foreign key linking to the events table
- `category_id`: Foreign key linking to the categories table
- Combined primary key ensures unique event-category pairs

#### Event_Registrations Table

The `event_registrations` table tracks student registrations for events:

```sql
CREATE TABLE event_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'attended') NOT NULL DEFAULT 'confirmed',
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (event_id, user_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

Key attributes:

- `id`: Unique identifier for each registration
- `event_id`: Foreign key linking to the events table
- `user_id`: Foreign key linking to the registered user
- `status`: Current registration status
- `registration_date`: Timestamp when registration occurred
- Unique constraint prevents duplicate registrations

#### Notifications Table

The `notifications` table stores system notifications for users:

```sql
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    is_read BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

Key attributes:

- `id`: Unique identifier for each notification
- `user_id`: Foreign key linking to the recipient user
- `content`: Notification message text
- `is_read`: Flag indicating whether the user has viewed the notification

### Database Relationships

The database schema implements several key relationships:

1. **One-to-One Relationships**:

   - User to User_Profile: Each user has exactly one profile record

2. **One-to-Many Relationships**:

   - User to Events: One user can create many events (as organizer)
   - User to Event_Registrations: One user can register for many events
   - User to Notifications: One user can receive many notifications
   - Event to Event_Registrations: One event can have many registrations

3. **Many-to-Many Relationships**:
   - Users to Categories (via User_Interests): Users can have multiple interests
   - Events to Categories (via Event_Categories): Events can belong to multiple categories

### Indexing Strategy

The database uses strategic indexing to optimize query performance:

1. **Primary Keys**: All tables have primary keys for efficient row lookup

2. **Foreign Keys**: All foreign key columns are indexed to speed up JOIN operations

3. **Additional Indexes**:
   - Events table: Index on `event_date` for date-based queries
   - Events table: Index on `status` for filtering published events
   - Notifications table: Index on `is_read` for filtering unread notifications
   - Event_Registrations table: Index on `status` for filtering by registration status

### Data Integrity Constraints

The schema implements several constraints to maintain data integrity:

1. **Referential Integrity**: Foreign key constraints ensure that references between tables remain valid

2. **Entity Integrity**: Primary key constraints prevent duplicate records

3. **Domain Integrity**:

   - ENUM types restrict values to valid options (e.g., user_type, event status)
   - NOT NULL constraints ensure required fields have values

4. **Unique Constraints**:
   - Users table: Email must be unique
   - Categories table: Category names must be unique
   - Event_Registrations table: One user can only register once per event

### Data Access Layer

The application accesses the database through a PDO (PHP Data Objects) abstraction layer, which provides:

1. **Prepared Statements**: Security against SQL injection

2. **Connection Pooling**: Efficient database connection management

3. **Transaction Support**: Ensuring atomic operations for critical processes

4. **Error Handling**: Consistent exception handling for database errors

### Sample Queries

#### Retrieving Events for a Student Dashboard

```sql
SELECT e.*, u.name as organizer_name
FROM events e
JOIN users u ON e.organizer_id = u.id
WHERE e.status = 'published'
AND e.event_date > NOW()
ORDER BY e.event_date ASC
LIMIT 10;
```

#### Finding Events Based on User Interests

```sql
SELECT DISTINCT e.*, u.name as organizer_name
FROM events e
JOIN users u ON e.organizer_id = u.id
JOIN event_categories ec ON e.id = ec.event_id
JOIN user_interests ui ON ec.category_id = ui.category_id
WHERE ui.user_id = ?
AND e.status = 'published'
AND e.event_date > NOW()
ORDER BY e.event_date ASC;
```

#### Retrieving a User's Registered Events

```sql
SELECT e.*, er.registration_date, er.status
FROM events e
JOIN event_registrations er ON e.id = er.event_id
WHERE er.user_id = ?
ORDER BY e.event_date ASC;
```

#### Getting Event Attendee Count

```sql
SELECT COUNT(*) as attendee_count
FROM event_registrations
WHERE event_id = ?
AND status IN ('confirmed', 'attended');
```

#### Retrieving User Notifications

```sql
SELECT *
FROM notifications
WHERE user_id = ?
ORDER BY created_at DESC
LIMIT 10;
```
