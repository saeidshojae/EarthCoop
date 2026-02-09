<p align="center">
  <a href="https://github.com/MoDarK-MK/NewEarthCoop" target="_blank">
    <img src="https://img.shields.io/github/v/release/MoDarK-MK/NewEarthCoop?style=flat-square&color=4CAF50" alt="Latest Release">
  </a>
  <a href="https://github.com/MoDarK-MK/NewEarthCoop/blob/main/LICENSE" target="_blank">
    <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="License">
  </a>
  <a href="https://github.com/MoDarK-MK/NewEarthCoop/stargazers" target="_blank">
    <img src="https://img.shields.io/github/stars/MoDarK-MK/NewEarthCoop?style=flat-square" alt="Stars">
  </a>
  <a href="https://github.com/MoDarK-MK/NewEarthCoop/network/members" target="_blank">
    <img src="https://img.shields.io/github/forks/MoDarK-MK/NewEarthCoop?style=flat-square" alt="Forks">
  </a>
</p>

<p align="center">
  <a href="https://github.com/MoDarK-MK/NewEarthCoop/issues" target="_blank">
    <img src="https://img.shields.io/github/issues/MoDarK-MK/NewEarthCoop?style=flat-square&color=ff6b6b" alt="Open Issues">
  </a>
  <a href="https://github.com/MoDarK-MK/NewEarthCoop/pulls" target="_blank">
    <img src="https://img.shields.io/github/issues-pr/MoDarK-MK/NewEarthCoop?style=flat-square&color=4CAF50" alt="Pull Requests">
  </a>
  <a href="https://github.com/MoDarK-MK/NewEarthCoop/commits/main" target="_blank">
    <img src="https://img.shields.io/github/last-commit/MoDarK-MK/NewEarthCoop?style=flat-square" alt="Last Commit">
  </a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-9.19-FF2D20?style=flat-square&logo=laravel" alt="Laravel">
  <img src="https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat-square&logo=php" alt="PHP">
  <img src="https://img.shields.io/badge/Vue.js-3-35495E?style=flat-square&logo=vuedotjs" alt="Vue.js">
  <img src="https://img.shields.io/badge/Bootstrap-5-7952B3?style=flat-square&logo=bootstrap" alt="Bootstrap">
  <img src="https://img.shields.io/badge/MySQL-8.0-00758F?style=flat-square&logo=mysql" alt="MySQL">
</p>

---

# 🌍 NewEarthCoop - Economic & Social Cooperation Platform

**NewEarthCoop** is a comprehensive and innovative platform for economic and social cooperation at local to global levels. This platform intelligently groups users based on personal characteristics and geographic locations, providing diverse tools for solving shared problems and executing collaborative projects.

## 🎯 Key Features

### 🔹 Intelligent User Grouping

- **Based on Personal Characteristics**: Age, gender, profession, specialty, expertise, and experience
- **Based on Geographic Location**: From neighborhood level to global scale
- **Automatic Grouping**: System automatically connects users to related groups
- **Organizational Levels**: Neighborhood, city, province, country, continent, and global

### 🔹 Interactive Tools

- **Messaging System**: Group chats and direct messaging between users and groups
- **Decision Making**: Surveys and innovative voting methods
- **Project Management**: Create, track, and manage collaborative projects
- **Transaction System**: Auctions, buying/selling, and peer-to-peer transactions

### 🔹 Economic Guidelines

- **Joint Investment**: Users can invest in projects together
- **Profit Sharing**: Project profits are distributed fairly
- **Asset Tracking**: Complete tracking of resources and invested capital
- **Transparent Auditing**: All transactions are recorded and auditable

### 🔹 Advanced Features

- **Authentication System**: Secure authentication using Laravel Sanctum
- **Multilingual Support**: Support for English, Persian, and other languages
- **Calendar Support**: Full support for both Gregorian and Persian (Jalali) calendars
- **Real-time Updates**: Live notifications with Pusher and Laravel Echo
- **RESTful API**: Complete API for mobile apps and external integrations
- **Admin Panel**: Comprehensive dashboard for system management
- **NajmHoda (AI Assistant)**: Smart assistant with chat capability for user support

## 🏗️ Technology Architecture

### Backend

```
Laravel 9.19
├── Authentication: Laravel Sanctum
├── API: RESTful with Sanctum
├── Database: MySQL 8.0
├── Cache: Redis
├── Queue: Laravel Queue
└── Real-time: Pusher + Laravel Echo
```

### Frontend

```
Vue.js 3 (Composition API)
├── UI Framework: Bootstrap 5
├── Editor: CKEditor 5
├── Date Picker: Persian Datepicker
├── Carousel: Swiper
├── HTTP Client: Axios
└── Build Tool: Vite
```

### Database

```
MySQL 8.0
├── 54+ Models
├── Relationships: Eloquent ORM
├── Migrations: Laravel Migrations
└── Seeders: Database Seeders
```

## 📦 Project Structure

```
NewEarthCoop/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/          # Admin panel controllers
│   │   │   ├── Auth/           # Authentication controllers
│   │   │   ├── Group/          # Group management controllers
│   │   │   ├── Profile/        # User profile controllers
│   │   │   └── API/            # API controllers
│   │   ├── Requests/           # Form Request Validation
│   │   └── Middleware/         # Custom Middleware
│   ├── Models/                 # Database Models (54+)
│   ├── Services/               # Business Logic
│   ├── Modules/
│   │   ├── NajmBahar/         # AI Smart Service
│   │   └── Blog/              # Blog System
│   ├── Helpers/               # Helper Functions
│   └── Events/                # Event Classes
├── routes/
│   ├── web.php               # Web Routes
│   ├── api.php               # API Routes
│   └── admin.php             # Admin Routes
├── resources/
│   ├── views/                # Blade Templates
│   │   ├── layouts/          # Layout Templates
│   │   ├── components/       # Vue Components
│   │   └── pages/            # Page Views
│   ├── js/                   # JavaScript/Vue Files
│   └── css/                  # CSS/SCSS Files
├── database/
│   ├── migrations/           # Database Migrations
│   ├── seeders/              # Database Seeders
│   └── factories/            # Model Factories
├── config/                   # Configuration Files
├── storage/                  # File Storage
│   └── najm-hoda/           # NajmHoda Knowledge Base
├── public/                   # Public Assets
└── tests/                    # Test Files
```

## 🚀 Installation & Setup

### Prerequisites

- PHP >= 8.0.2
- MySQL >= 8.0
- Node.js >= 16
- Composer >= 2.0
- Redis (optional)

### Installation Steps

1. **Clone the Repository**

```bash
git clone https://github.com/MoDarK-MK/NewEarthCoop.git
cd NewEarthCoop
```

2. **Install PHP Dependencies**

```bash
composer install
```

3. **Install JavaScript Dependencies**

```bash
npm install
```

4. **Create .env File**

```bash
cp .env.example .env
```

5. **Generate Application Key**

```bash
php artisan key:generate
```

6. **Configure Database**

```bash
# Edit .env file
nano .env
# Set DB_CONNECTION, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
```

7. **Run Migrations**

```bash
php artisan migrate
```

8. **Run Seeders (Optional)**

```bash
php artisan db:seed
```

9. **Build Assets**

```bash
npm run build
```

10. **Start Development Server**

```bash
php artisan serve
```

Then visit `http://localhost:8000`

## 📋 Important Configuration

### Enable NajmHoda (AI Service)

```env
# .env
OPENAI_API_KEY=your_openai_key_here
NAJM_HODA_ENABLED=true
```

### Setup Pusher (Real-time)

```env
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=mt1
```

### Configure Mail

```env
MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
```

## 🔑 Role-Based Access Control (RBAC)

The system implements role-based access control:

- **Super Admin**: Full system access
- **Admin**: User and group management
- **Moderator**: Group supervision
- **Group Leader**: Lead their own group
- **User**: Regular user access

## 🧪 Testing

### Run Unit Tests

```bash
php artisan test
```

### Run Specific Tests

```bash
php artisan test --filter=UserTest
```

### Generate Test Coverage

```bash
php artisan test --coverage
```

## 📚 API Documentation

### Authentication

```
POST /api/auth/register
POST /api/auth/login
POST /api/auth/logout
GET  /api/auth/user
```

### Groups

```
GET    /api/groups
POST   /api/groups
GET    /api/groups/{id}
PUT    /api/groups/{id}
DELETE /api/groups/{id}
```

### Projects

```
GET    /api/projects
POST   /api/projects
GET    /api/projects/{id}
PUT    /api/projects/{id}
DELETE /api/projects/{id}
```

### Messages

```
GET    /api/messages
POST   /api/messages
DELETE /api/messages/{id}
```

### NajmHoda (AI Chat)

```
POST /api/najm-hoda/chat
GET  /api/najm-hoda/conversations
GET  /api/najm-hoda/conversations/{id}
```

For comprehensive API documentation, see the Postman collection or API Documentation.

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. **Fork** the repository
2. **Create** a feature branch: `git checkout -b feature/AmazingFeature`
3. **Commit** your changes: `git commit -m 'Add some AmazingFeature'`
4. **Push** to the branch: `git push origin feature/AmazingFeature`
5. **Submit** a Pull Request

### Code Standards

- PSR-12 for PHP code
- Vue.js Best Practices for JavaScript
- Use Laravel Artisan for class generation

## 📝 License

This project is open-source software licensed under the [MIT license](LICENSE).

## 👨‍💻 Authors

- **saeidshojae** - Project Creator

## 📞 Support & Contact

- **Issues**: Report bugs and request features
- **Discussions**: General questions and discussions
- **Email**: Contact via GitHub

## 🎓 Learning Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Vue.js 3 Guide](https://vuejs.org)
- [Bootstrap Documentation](https://getbootstrap.com)
- [MySQL Documentation](https://dev.mysql.com/doc)

## 📊 Project Status

| Component              | Status         | Description             |
| ---------------------- | -------------- | ----------------------- |
| Core System            | ✅ Complete    | Main project system     |
| Authentication         | ✅ Complete    | Authentication system   |
| Groups & Collaboration | ✅ Complete    | Group management system |
| Projects Management    | ✅ Complete    | Project management      |
| Real-time Features     | ✅ Complete    | Pusher integration      |
| NajmHoda AI            | ✅ Active      | Smart assistant         |
| Mobile App             | 🔄 In Progress | Mobile application      |
| Advanced Analytics     | 🔄 In Progress | Advanced analytics      |

## 🐛 Reporting Issues

If you encounter a problem, please:

1. Check if the issue has already been reported
2. Provide detailed information about the issue
3. Mention your PHP and Laravel versions
4. Include steps to reproduce the issue

## 🎉 Conclusion

NewEarthCoop represents a modern approach to economic and social cooperation in the digital age. We are committed to creating a fairer and more collaborative world.

---

<p align="center">
  <strong>Made with ❤️ by s.shojaei</strong>
</p>

<p align="center">
  <a href="https://github.com/saeidshojae/NewEarthCoop">⭐ Star us on GitHub!</a>
</p>
#   N e w E a r t h C o o p N e w  
 