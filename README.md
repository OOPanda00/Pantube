# Pantube — Video Sharing Platform

A lightweight, custom PHP video sharing application inspired by platforms like YouTube. Built with vanilla PHP (no framework) and MySQL, featuring user authentication, video uploads, commenting, messaging, and social features.

**PHP Version:** 7.4+  
**Database:** MySQL/MariaDB  
**License:** Unlicensed (private project)

---

## Quick Start

### Prerequisites

- **PHP 7.4** or higher with CLI support
- **MySQL 5.7** or **MariaDB 10.3**+ (with InnoDB)
- **FFmpeg** (for video thumbnail generation)
- **Linux/Mac** for best compatibility (Windows may require WSL)

### Installation

1. **Clone the repository** into your web root:
   ```bash
   cd /var/www/html
   git clone <repo-url> pantube
   cd pantube
   ```

2. **Create `.env` file** at project root with database credentials:
   ```bash
   cp .env.example .env  # if available, or create manually
   cat > .env << 'EOF'
   APP_ENV=development
   APP_DEBUG=true
   APP_NAME=Pantube
   APP_URL=https://localhost
   APP_KEY=base64:your_random_32_char_key_here
   APP_TIMEZONE=UTC
   
   DB_HOST=YOUR_HOST
   DB_USERNAME=YOUR_USER
   DB_PASSWORD=YOUR_PASS
   DB_NAME=YOUR_DB_NAME
   
   UPLOAD_MAX_SIZE=500MB
   UPLOAD_VIDEO_TYPES=mp4
   UPLOAD_IMAGE_TYPES=jpg,jpeg,png
   
   VIDEO_QUEUE_ENABLED=false
   VIDEO_FORMATS=mp4
   VIDEO_RESOLUTIONS=360,720
   VIDEO_BITRATES=500k,1000k
   
   CACHE_DRIVER=file
   CACHE_PREFIX=pantube_
   REDIS_HOST=127.0.0.1
   REDIS_PORT=6379
   
   TWOFA_ENABLED=false
   PWA_ENABLED=false
   CDN_ENABLED=false
   EOF
   ```

3. **Set up database:**
   ```bash
   # Create database
   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS pandaa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   
   # Load migrations
   mysql -u root -p pandaa < sql/migrations.sql
   ```
   
   **Base tables you need to create manually** (not in migrations.sql):
   ```sql
   CREATE TABLE users (
       id INT AUTO_INCREMENT PRIMARY KEY,
       username VARCHAR(255) UNIQUE NOT NULL,
       email VARCHAR(255) UNIQUE NOT NULL,
       password VARCHAR(255) NOT NULL,
       avatar VARCHAR(255) DEFAULT NULL,
       bio TEXT,
       role ENUM('user','admin','owner') DEFAULT 'user',
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       INDEX idx_email (email)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   
   CREATE TABLE videos (
       id INT AUTO_INCREMENT PRIMARY KEY,
       user_id INT NOT NULL,
       title VARCHAR(255) NOT NULL,
       description TEXT,
       filename VARCHAR(255) NOT NULL UNIQUE,
       thumbnail VARCHAR(255),
       views INT DEFAULT 0,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       INDEX idx_user (user_id),
       INDEX idx_created (created_at),
       FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   
   CREATE TABLE comments (
       id INT AUTO_INCREMENT PRIMARY KEY,
       user_id INT NOT NULL,
       video_id INT NOT NULL,
       parent_id INT DEFAULT NULL,
       content TEXT NOT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       INDEX idx_video (video_id),
       INDEX idx_user (user_id),
       FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
       FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
       FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   
   CREATE TABLE likes (
       id INT AUTO_INCREMENT PRIMARY KEY,
       user_id INT NOT NULL,
       video_id INT NOT NULL,
       type ENUM('like','dislike') NOT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       UNIQUE KEY unique_user_video (user_id, video_id),
       INDEX idx_video (video_id),
       FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
       FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   
   CREATE TABLE chat_messages (
       id INT AUTO_INCREMENT PRIMARY KEY,
       sender_id INT NOT NULL,
       receiver_id INT NOT NULL,
       message TEXT NOT NULL,
       is_read BOOLEAN DEFAULT FALSE,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       INDEX idx_sender (sender_id),
       INDEX idx_receiver (receiver_id),
       INDEX idx_created (created_at),
       FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
       FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   
   CREATE TABLE notifications (
       id INT AUTO_INCREMENT PRIMARY KEY,
       user_id INT NOT NULL,
       type VARCHAR(50) NOT NULL,
       source_user_id INT,
       video_id INT,
       comment_id INT,
       is_read BOOLEAN DEFAULT FALSE,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       INDEX idx_user (user_id),
       INDEX idx_created (created_at),
       FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
       FOREIGN KEY (source_user_id) REFERENCES users(id) ON DELETE SET NULL,
       FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
       FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```

4. **Create required directories:**
   ```bash
   mkdir -p public/uploads/{videos,thumbnails,avatars}
   mkdir -p logs
   chmod 755 public/uploads logs
   chmod 755 public/uploads/videos public/uploads/thumbnails public/uploads/avatars
   ```

5. **Verify FFmpeg is installed:**
   ```bash
   which ffmpeg
   # If not installed:
   # Ubuntu/Debian: sudo apt-get install ffmpeg
   # macOS: brew install ffmpeg
   ```

6. **Run the development server:**
   ```bash
   php -S localhost:8000 -t public
   ```
   
   Then open `http://localhost:8000/pantube/public` in your browser.

---

## Project Structure

```
pantube/
├── app/
│   ├── config/
│   │   ├── config.php          # Main config (reads .env)
│   │   ├── database.php        # DB credentials
│   │   └── security.php        # Security settings
│   ├── core/
│   │   ├── App.php             # Bootstrap & autoloader
│   │   ├── Auth.php            # Authentication & session
│   │   ├── Cache.php           # Caching (Redis/file fallback)
│   │   ├── Controller.php      # Base controller class
│   │   ├── CSRF.php            # CSRF token generation/validation
│   │   ├── Database.php        # PDO singleton
│   │   ├── Mentions.php        # @mention processing
│   │   ├── TwoFA.php           # Two-factor auth (TOTP)
│   │   └── VideoProcessor.php  # Video encoding (unused)
│   ├── controllers/
│   │   ├── AdminController.php
│   │   ├── AuthController.php
│   │   ├── ChatController.php
│   │   ├── CommentController.php
│   │   ├── LikeController.php
│   │   ├── MessagesController.php
│   │   ├── NotificationController.php
│   │   ├── ProfileController.php
│   │   ├── SearchController.php
│   │   └── VideoController.php
│   ├── models/
│   │   ├── Chat.php
│   │   ├── Comment.php
│   │   ├── Like.php
│   │   ├── Notification.php
│   │   ├── User.php
│   │   └── Video.php
│   └── views/
│       ├── home.php
│       ├── login.php
│       ├── register.php
│       ├── upload.php
│       ├── watch.php
│       ├── profile.php
│       ├── messages.php
│       ├── notifications.php
│       ├── layout/
│       │   ├── header.php
│       │   ├── footer.php
│       │   └── sidebar.php
│       └── partials/
│           └── comments.php
├── public/
│   ├── index.php               # Router & dispatcher
│   ├── manifest.json           # PWA manifest (unused)
│   ├── sw.js                   # Service worker (unused)
│   ├── assets/
│   │   ├── css/style.css
│   │   └── js/app.js
│   └── uploads/                # User-generated files
│       ├── videos/
│       ├── thumbnails/
│       └── avatars/
├── sql/
│   └── migrations.sql          # Schema (incomplete - see Installation)
├── logs/
│   └── error.log               # App error log
├── .env                        # Environment variables
├── .github/
│   └── copilot-instructions.md # AI agent guide
└── README.md                   # This file
```

---

## Routing & Controllers

Routes are defined in [public/index.php](public/index.php) with manual switch statements. The first URI segment selects the action:

| Route | Method | Controller | Handler | Notes |
|-------|--------|------------|---------|-------|
| `/` | GET | VideoController | `index()` | Home, list videos |
| `/watch/{id}` | GET | VideoController | `watch()` | Watch video + comments |
| `/upload` | GET/POST | VideoController | `uploadForm()` / `upload()` | Upload video form & submission |
| `/delete?id={id}` | GET | VideoController | `delete()` | Delete own video |
| `/login` | GET/POST | AuthController | `loginForm()` / `login()` | Login form & submission |
| `/register` | GET/POST | AuthController | `registerForm()` / `register()` | Registration |
| `/logout` | GET | AuthController | `logout()` | Session destruction |
| `/profile[?id={id}]` | GET | ProfileController | `show()` | View profile (own or other user's) |
| `/profile_update` | POST | ProfileController | `update()` | Update profile fields |
| `/comment` | POST | CommentController | `store()` | Add comment (AJAX) |
| `/comment_delete?id={id}` | POST | CommentController | `delete()` | Delete comment |
| `/messages` | GET | MessagesController | `index()` | Message list |
| `/messages/conversation/{user_id}` | GET | MessagesController | `conversation()` | View conversation |
| `/messages/start` | POST | MessagesController | `start()` | Start new conversation |
| `/chat_send` | POST | ChatController | `send()` | Send message (AJAX) |
| `/chat_fetch?user_id={id}` | GET | ChatController | `fetch()` | Fetch messages (AJAX) |
| `/like` | POST | LikeController | `toggle()` | Toggle like/dislike |
| `/search?q={query}` | GET | SearchController | `index()` | Search videos/users |
| `/admin` | GET | AdminController | `dashboard()` | Admin panel |
| `/admin_promote?id={id}` | POST | AdminController | `promote()` | Promote to admin (owner only) |
| `/admin_demote?id={id}` | POST | AdminController | `demote()` | Demote admin (owner only) |
| `/notifications` | GET | NotificationController | `index()` | Notification list |
| `/notifications_read?id={id}` | POST | NotificationController | `markRead()` | Mark notification read |
| `/notifications_check` | GET | NotificationController | `checkNew()` | Check new (AJAX) |

---

## Key Features

### ✅ Implemented

- **Authentication**: Secure password hashing (PASSWORD_DEFAULT), session management with timeout
- **Video Upload & Playback**: MP4 upload, automatic thumbnail generation via FFmpeg
- **Comments**: Threaded comments with parent/child relationships
- **Messaging**: Private 1-on-1 user messaging with unread count
- **Social**: Follow/unfollow, friend requests, block users
- **Likes & Dislikes**: Toggle like/dislike on videos
- **Admin Panel**: Promote/demote users to admin role
- **CSRF Protection**: Token generation, validation, expiry, usage limits
- **Search**: Full-text search on videos (title/description) and users
- **Notifications**: Activity notifications (video upload, comments, replies)
- **Caching**: Redis + file-based fallback with TTL per entity type
- **Two-Factor Auth (2FA)**: TOTP-based, configurable via `config.php`
- **User Profiles**: Avatar, bio, user stats, activity

### ⚠️ Partially Implemented / Unused

- **PWA Features** (`manifest.json`, `sw.js`) — not integrated
- **Video Processor** — class exists but not used in VideoController
- **Activity Logs** — table created in migrations but no logging code
- **Backup System** — backup history table created but no backup logic
- **Mentions** (`Mentions.php`) — not integrated
- **Video Encoding Queues** — config exists but not implemented

### ❌ Missing / Issues

See [Known Issues](#known-issues) below.

---

## Configuration

Edit [app/config/config.php](app/config/config.php) via environment variables in `.env`:

### Core Settings
- `APP_ENV` — `development` or `production` (controls error display)
- `APP_DEBUG` — `true`/`false` (display errors)
- `APP_NAME` — App name
- `APP_URL` — Base URL for redirects
- `APP_KEY` — 32-char key (base64: prefix supported) for CSRF signing
- `APP_TIMEZONE` — Default timezone

### Database
- `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_NAME`

### File Uploads
- `UPLOAD_MAX_SIZE` — e.g. `500MB` (string parsed to bytes)
- `UPLOAD_VIDEO_TYPES` — Comma-separated, e.g. `mp4`
- `UPLOAD_IMAGE_TYPES` — Comma-separated, e.g. `jpg,jpeg,png`
- Upload directory: `public/uploads` (hardcoded in code)

### Video Processing
- `VIDEO_QUEUE_ENABLED` — Enable async processing (not implemented)
- `VIDEO_FORMATS`, `VIDEO_RESOLUTIONS`, `VIDEO_BITRATES` — Format strings
- `VIDEO_WATERMARK_ENABLED`, `VIDEO_WATERMARK_PATH` — Watermark settings

### Caching
- `CACHE_DRIVER` — `file`, `redis`, or `apc`
- `CACHE_PREFIX` — Key prefix
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`

### Security
- `TWOFA_ENABLED` — Enable 2FA
- `TWOFA_ISSUER`, `TWOFA_DIGITS`, `TWOFA_PERIOD`, `TWOFA_WINDOW`

### CDN & PWA
- `CDN_ENABLED` — Serve files from CDN
- `PWA_ENABLED`, `PWA_NAME`, `PWA_THEME_COLOR` — PWA settings (not integrated)

---

## Security Notes

### Strengths
- **CSRF Protection**: Token generation, HMAC validation, expiry, usage limits
- **Password Security**: `password_hash(PASSWORD_DEFAULT)` for new users
- **Session Timeout**: 2-hour idle timeout
- **Login Attempt Tracking**: Recorded with IP, email/identifier, reason
- **2FA Support**: TOTP-based with configurable window
- **SQL Injection Prevention**: PDO prepared statements throughout
- **Input Escaping**: `htmlspecialchars()` in forms
- **Admin Checks**: `Auth::isAdmin()`, `Auth::isOwner()` guards

### Weaknesses & To-Do
- **Database config hardcoded** (`app/config/database.php`) — should move to `.env`
- **No HTTPS enforcement** — add redirect in production
- **Session ID regeneration**: Called in `Auth::login()` only, not on role changes
- **No rate limiting** — consider adding per-IP request throttling
- **Old logins not cleared** — `login_attempts` table grows indefinitely
- **User-generated content not validated** for dangerous tags/scripts
- **No API authentication** — if REST endpoints added, implement JWT/Bearer tokens
- **Passwords not enforced** — no minimum strength requirements

---

## Development Workflow

### Local Development
```bash
# Start server
php -S localhost:8000 -t public

# Watch logs
tail -f logs/error.log

# Check DB (if MySQL CLI installed)
mysql -u root -p pandaa -e "SELECT * FROM videos;"
```

### Database Changes
1. **Edit** [sql/migrations.sql](sql/migrations.sql) or create new migrations
2. **Run** migrations: `mysql -u root -p pandaa < sql/migrations.sql`
3. **Verify**: Check table structure with `SHOW TABLES;` and `DESCRIBE table_name;`
4. **Update** models if schema changes

### Adding a New Controller
1. Create file: `app/controllers/MyController.php`
2. Class must be named `MyController` (matches filename exactly)
3. Extend `Controller` base class
4. Add route logic to [public/index.php](public/index.php) switch statement
5. Call `$this->view('viewname', $data)` or `$this->redirect('path')`

### Adding a New Model
1. Create file: `app/models/MyModel.php`
2. Class must be named `MyModel`
3. Use `Database::getInstance()` for DB access
4. Use PDO prepared statements: `$db->prepare()` + `$stmt->execute()`
5. Return arrays (FETCH_ASSOC mode)

### Testing File Upload
```bash
# Create test video (5MB MP4)
ffmpeg -f lavfi -i testsrc=duration=1:size=640x480:rate=1 -pix_fmt yuv420p /tmp/test.mp4

# Upload via form at /upload
```

### Debugging
- **Enable debug mode**: Set `APP_DEBUG=true` and `APP_ENV=development` in `.env`
- **Check logs**: `logs/error.log` contains all errors
- **Session debug**: `var_dump($_SESSION);` in views
- **DB debug**: Add `echo $sql;` before PDO `execute()`

---

## Known Issues & To-Do

### Critical Bugs 🔴

1. **Missing base tables** (`users`, `videos`, `comments`, etc.)
   - Migrations only contain ALTER/INDEX operations
   - Manual CREATE TABLE statements required (see Installation)
   - **Fix**: Add CREATE TABLE statements to `sql/migrations.sql`

2. **Database config hardcoded** 
   - `app/config/database.php` has plaintext `root:123456`
   - **Fix**: Move to `.env` with `env()` helper, update Database::getInstance()

3. **Auth::canManageAdmins() missing**
   - Called in `AdminController` but not defined in `Auth::class`
   - **Fix**: Add method to `Auth.php`: 
     ```php
     public static function canManageAdmins(): bool {
         return self::check() && ($_SESSION['user']['role'] ?? '') === 'owner';
     }
     ```

4. **Chat model missing**
   - `Chat.php` exists but is empty; `ChatController` calls undefined methods
   - **Fix**: Implement `Chat::send()`, `Chat::fetch()` in `Chat.php`

5. **LikeController missing**
   - No `app/controllers/LikeController.php` file
   - Router calls `(new LikeController())->toggle()`
   - **Fix**: Create controller with AJAX response:
     ```php
     public function toggle(): void {
         if (!Auth::check()) { http_response_code(403); exit; }
         Like::toggle(Auth::user()['id'], (int)$_POST['video_id'], $_POST['type']);
         echo json_encode(['success' => true]);
     }
     ```

6. **ChatController undefined**
   - File `ChatController.php` doesn't exist in workspace
   - **Fix**: Create at `app/controllers/ChatController.php`

7. **NotificationController incomplete**
   - Routes reference it but implementation unknown
   - **Fix**: Verify file exists and methods implemented

### Major Issues 🟠

8. **No input validation on upload**
   - File extension checked only (`mp4`), no MIME type, no size check pre-upload
   - **Fix**: Add before `move_uploaded_file()`:
     ```php
     if ($_FILES['video']['size'] > App::$config['upload']['max_video_size']) {
         exit('File too large');
     }
     ```

9. **FFmpeg path not configurable**
   - Hardcoded `exec('ffmpeg ...')` in `VideoController::upload()`
   - May fail if FFmpeg not in PATH
   - **Fix**: Add `FFMPEG_PATH` to config, use it in exec

10. **No pagination on home page**
    - `Video::latest(20)` returns exactly 20, no "load more" or next page
    - **Fix**: Add page parameter to `VideoController::index()`

11. **Search has no LIMIT on FOUND_ROWS()**
    - Could be expensive on large tables
    - **Fix**: Consider caching or indexing strategy

12. **Password not hashed on old registration path**
    - `AuthController::register()` hashes password ✓
    - `User::create()` does not — caller must hash
    - **Fix**: Enforce hashing in model or add validation

13. **Session timeout not enforced on logout**
    - `Auth::check()` logs out on timeout, but old session still exists
    - **Fix**: Call `session_destroy()` explicitly in timeout branch

### Minor Issues 🟡

14. **Unused code**
    - `VideoProcessor.php` — never instantiated
    - `Mentions.php` — not integrated
    - PWA files (`manifest.json`, `sw.js`) — not served
    - **Fix**: Remove or complete before production

15. **Console errors likely**
    - No view files provided (`home.php`, `watch.php`, etc.)
    - Frontend likely has JS errors if scripts missing
    - **Fix**: Provide or create stub views

16. **No 404 page**
    - Default action returns plain text "404 Not Found"
    - **Fix**: Create `views/404.php`, call `$this->view('404')`

17. **Notification creation assumes fields**
    - `Notification::create()` not shown, assumed to exist
    - If missing, notifications will fail silently
    - **Fix**: Ensure `Notification.php` implements create/insert

18. **Comments marked as pending approval?**
    - Migrations create `processing_status` on videos but no comment moderation
    - **Fix**: Decide if comments need approval, implement workflow

19. **User stats in profile**
    - `User::getStats()` called but not shown in code
    - **Fix**: Implement in `User.php` or create view variable

20. **No tests**
    - Zero test files or test framework
    - **Fix**: Add PHPUnit or similar for critical functions

---

## API Endpoints (AJAX)

These endpoints are called via JavaScript:

```
POST /chat_send              — Send message to user
GET  /chat_fetch?user_id={id} — Get message history
POST /comment                — Post comment on video (JSON or form)
POST /comment_delete?id={id}  — Delete comment
POST /like                   — Like/dislike video
GET  /notifications_check     — Check for new notifications
POST /notifications_read?id={id} — Mark notification as read
POST /profile_update         — Update profile (action param)
GET  /search?q={query}       — Search videos/users
```

All require:
- User authentication (`Auth::check()`)
- Valid CSRF token in `_csrf` form field or `X-CSRF-TOKEN` header
- Proper HTTP method (GET/POST)

---

## File Structure & Limits

### Upload Directories
- **Videos**: `public/uploads/videos/` — named `video_*.mp4`, max 500MB (configurable)
- **Thumbnails**: `public/uploads/thumbnails/` — named `thumb_*.jpg`, auto-generated
- **Avatars**: `public/uploads/avatars/` — named `{timestamp}_{hash}.{ext}`, max 10MB

### Permissions
```bash
chmod 755 public/uploads
chmod 755 public/uploads/videos
chmod 755 public/uploads/thumbnails
chmod 755 public/uploads/avatars
```

### Cleanup
Videos and thumbnails are **not** auto-deleted; manually remove from disk when deleting records.

---

## Performance Considerations

- **Caching**: Enabled by default (Redis with file fallback)
  - User: 30 min
  - Video: 1 hour
  - Comments: 15 min
- **Database**: Indexes on `user_id`, `created_at`, `views`, `(video_id, created_at)`
- **Fulltext**: Search indexes on `title|description` (videos), `username|email` (users), `content` (comments)
- **No pagination on home**: Loads all 20 latest videos on every visit

---

## Troubleshooting

### "Database connection error"
- Check credentials in `app/config/database.php`
- Verify MySQL is running: `sudo service mysql status`
- Test connection: `mysql -h localhost -u root -p -e "SELECT 1;"`

### FFmpeg not found
- Verify installed: `which ffmpeg`
- Add to PATH or update `config.php` with full path

### "Class not found" errors
- Check filename matches class name exactly (case-sensitive on Linux)
- Class must be in `app/core`, `app/controllers`, or `app/models`

### "Table not found" 
- Run migrations: `mysql -u root -p pandaa < sql/migrations.sql`
- Create base tables (see Installation section)

### Session issues
- Verify `logs/` directory exists and is writable
- Check `session.save_path` in `php.ini`: `php -i | grep session.save_path`
- Ensure cookies are enabled (check browser DevTools)

### Upload fails
- Verify `public/uploads/videos` is writable
- Check file size doesn't exceed `UPLOAD_MAX_SIZE` in config
- Verify FFmpeg is installed for thumbnail generation

---

## Contributing

1. Follow existing code style (PSR-12 where practical)
2. Use PDO prepared statements for all queries
3. Always validate/escape user input
4. Test locally before pushing
5. Update this README for schema or routing changes
6. Reference issues when making changes

---

## License

Private project. Unlicensed.

---

## Support & Contact

For issues or questions, contact the development team or review logs at `logs/error.log`.

---

**Last Updated:** February 3, 2026  
**Status:** Development (pre-production, critical issues remain)
