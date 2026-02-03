<!-- Copilot instructions for working on the Pantube PHP app -->
# Pantube — Quick AI assistant guide

This file contains the minimal, actionable knowledge an AI coding agent needs to be productive in this repository.

## Project Status (As of February 4, 2026 — POST-STABILITY EVOLUTION)
- ✅ **Core functionality**: All major features implemented and working (videos, auth, comments, likes, notifications, messaging)
- ✅ **UI/UX**: Premium responsive CSS with micro-interactions, animations, accessibility, and evolution-phase polish
- ✅ **Database**: Comprehensive schema with users, videos, comments, likes, chat_messages, notifications, login_attempts, remember_tokens, video_formats, activity_logs, backup_history tables, proper relationships, cascading deletes, indexes
- ✅ **User features**: Follow/Unfollow toggle, Friend requests, Direct messaging (1-on-1 conversations)
- ✅ **Accessibility**: Skip link, keyboard navigation, focus states, screen reader support, reduced motion, Escape to close dropdown/sidebar, aria-expanded on comment toggle
- ✅ **Performance**: Smooth animations (150-300ms), passive scroll, debounced header, scoped DOM queries
- ✅ **Polish**: Loading states, error feedback, empty states (home, profile, watch, notifications, messages), comment toggle, like-ratio bar, notification layout (avatar + content), message send feedback (“Sent!”), dark theme CSS foundation
- ✅ **Profile edit (own profile)**: Change display name (username), bio, password, avatar; card-based UI with flash messages (success/error); friend request accept/reject from profile uses `user_id` (controller resolves `request_id`)
- 📌 **Operating mode**: Evolution only — no breaking changes, no route changes, no feature removal. Forward-only improvements.

## Architecture Overview

- **Big picture**: A small custom MVC-ish PHP 7.4+ app (no framework). Bootstrapping is in `app/core/App.php` which loads config and autoloads classes. Routing is manual in `public/index.php` — the first URI segment selects the controller/action (e.g. `watch`, `upload`, `login`). Controllers live in `app/controllers/`, models in `app/models/`, and views in `app/views/`.

- **Routing & controllers**: `public/index.php` maps segment -> controller. Example: `/pantube/public/watch/123` sets `$_GET['id']=123` then calls `(new VideoController())->watch()`. Controller methods use `$this->view('viewname', $data)` which includes `app/views/layout/header.php`, the view, and `footer.php`. Routes include home, watch, upload, login/register, profile, messages, search, notifications, admin functions, and chat.

- **Configuration**: `app/config/config.php` reads a `.env` file at project root. Important entries:
  - `paths` (root, app, public, uploads, logs, backup)
  - `upload` (max sizes, allowed types, chunk_size)
  - `video` (ffmpeg/encoding settings, resolutions, bitrates, watermark, CDN config)
  - `cache` (Redis/file driver, TTL settings)
  - `twofa` (2FA settings)
  - `cdn` (CDN configuration)
  - `pwa` (PWA settings)
  - `APP_ENV=development` enables error display; use `production` for live

- **Models & DB**: Models use `Database::getInstance()` and PDO prepared statements. Methods are mostly static (e.g. `Video::create`, `Video::latest`, `Video::find`). See `app/models/Video.php` for examples. Migrations live in `sql/migrations.sql`.

- **File handling & external tools**: Uploads are stored under `public/uploads` (`videos`, `thumbnails`, `avatars`). Video thumbnail generation uses `ffmpeg` invoked via `exec()` in `app/controllers/VideoController.php` — ensure `ffmpeg` is available on the host when changing upload/encoding logic.

- **Caching**: `app/core/Cache.php` provides Redis with file fallback. Clear cache keys after CRUD operations to ensure fresh data. Example: `Cache::delete('videos_latest_20')` after video deletion.

- **Security & session**: CSRF tokens are generated/validated via the `CSRF` utility (see `app/core/CSRF.php`). Authentication helper is `app/core/Auth.php` with methods `Auth::check()`, `Auth::user()`, `Auth::isAdmin()`, `Auth::isOwner()`. Session timeout is 2 hours. Follow existing permission checks when adding endpoints.

- **Two-Factor Authentication**: Enabled via `TwoFA::generate()` and `TwoFA::verify()`. TOTP secrets stored in `users.twofa_secret`, backup codes in `users.twofa_backup_codes`. Controlled by `users.twofa_enabled` flag.

## Conventions & Patterns

- **Class autoloading**: Files must be named exactly like the class and located in `app/core`, `app/controllers`, or `app/models` (see `App::run()` autoloader). Example: class `VideoController` → file `app/controllers/VideoController.php`.
- **Controllers**: Thin layer — prepare data, call `$this->view()` or `$this->redirect()`. Use `CSRF::generate()` for forms, `Auth::check()` for permissions.
- **Models**: Procedural/static wrappers around PDO operations (no ORM). Use `Database::getInstance()` for connection, `prepare()` + `execute()` for queries. Return arrays from finders.
- **Views**: Plain PHP templates; manual escaping with `htmlspecialchars()`. Use Bootstrap-compatible utility classes (mb-*, mt-*, p-*, text-*, etc.) from `public/assets/css/style.css`.
- **JavaScript**: Event delegation for dynamic elements. Use `fetch()` for async requests. Selector scoping with `container.querySelector()` to avoid global conflicts.

## Recent Improvements (Feb 2026)

1. **CSS Redesign**: Modern responsive design (single `style.css`, 2700+ lines)
   - Mobile-first: base styles for mobile, breakpoints at 576px, 768px, 1024px, 1440px
   - Video grid: 1 col (mobile) → 2/3/4/5 cols at breakpoints
   - CSS variables: `--primary-color`, `--space-*`, `--radius-*`, transitions
   - Form styling, modals, alerts, badges, hover/focus states

2. **Like Button**: Scoped DOM selectors (`container.querySelector()`) so likes update per-video without conflict

3. **Notifications**: Dropdown (header) with avatar + content layout, mark-read button, Escape to close; slide panel also available

4. **Search Bar**: Mobile-friendly; search bar in header at 576px+ with proper flex layout

5. **Video Deletion**: Cache clearing (`videos_latest_20`, `videos_all`), hover UX

6. **Post-Stability Evolution (Feb 4, 2026)** — evolution-only, zero breaking changes:
   - **Empty states**: Home, profile, watch suggested, notifications, messages use `.empty-state` with icon + heading + short copy
   - **Comments**: Toggle show/hide (Hide/Show Comments), full comment form/header/avatar/actions/reply styling, `.comments-section.collapsed`
   - **Like ratio**: Bar + fill + text on watch page
   - **Notifications**: Item layout (avatar, content, meta, actions), `.no-notifications`, focus-visible
   - **Messages**: `.messages-page`, `.conversation-item`, `.search-results.show`, conversation header/back btn/peer avatar
   - **Accessibility**: Skip link (`#main-content`), `main` id + tabindex="-1", Escape closes notifications dropdown and sidebar, `aria-expanded` on comment toggle
   - **JS**: Comment toggle handler, Escape key handlers, message send feedback (“Sent!” for 1.5s), comment delete scoped to `.comment-actions .delete-btn` / `[data-comment-id] .delete-btn` (video delete unchanged)
   - **Dark theme**: `body.theme-dark` CSS variables (foundation; toggle already in JS)
   - **PHP**: Skip link + main id in header; empty-state wrappers in home, profile, watch; login link uses `var(--primary-color)`

7. **Profile page — Edit profile (own profile)** — professional UI, all in one go:
   - **Edit section** (only when viewing own profile): “Edit profile” heading + 4 cards in a responsive grid (1 col mobile, 2 cols 768px+).
   - **Avatar**: Form `profile_update` + `action=update_avatar`, `enctype=multipart/form-data`, file input (JPG/PNG/WebP). Preview updates on file select (JS). Controller: `updateAvatar()` — validates, saves to `uploads/avatars/`, `User::updateAvatar()`, session update, flash success/error.
   - **Display name**: Form `profile_update` + `action=update_username`, text input (3–30 chars). Controller: `updateUsername()` — uniqueness check, `User::updateUsername()`, session update.
   - **Bio**: Form `profile_update` + `action=update_bio`, textarea (max 500 chars), live character count (JS). Controller: `updateBio()`.
   - **Password**: Form `profile_update` + `action=update_password`, old_password, new_password, confirm_password (min 6 chars). Controller: `updatePassword()` — verify old, then update hash.
   - **Flash messages**: Profile view reads `$_SESSION['success']` and `$_SESSION['error']`, displays `.alert.alert-success` / `.alert.alert-danger`, then unsets them.
   - **CSS**: `.profile-edit`, `.profile-edit-grid`, `.profile-edit-card`, `.profile-edit-card-title`, `.profile-edit-form`, `.profile-edit-label`, `.profile-edit-input`, `.profile-edit-textarea`, `.profile-edit-file`, `.profile-edit-avatar-preview`, `.profile-edit-submit`, `.profile-edit-hint`; responsive grid; focus/hover states.
   - **Friend requests from profile**: `accept_friend_request` / `reject_friend_request` now accept `user_id` (sender); `User::getPendingFriendRequestId($senderId, $receiverId)` used to resolve `request_id` when not provided.

## Premium Enhancements (Feb 2026 - Evolution Phase)

### Micro-Interactions & Polish
- **Smooth Transitions**: All interactions use CSS transitions (150-300ms) with easing functions
- **Loading States**: Buttons show feedback (⏳ icon) during async operations, then revert on success/error
- **Button Ripple Effects**: CSS-based ripple animation on button clicks for tactile feedback
- **Hover Effects**: Subtle transforms (translateY, scale) on interactive elements, shadow depth changes
- **Animations**: Slide-down dropdowns, fade-in content, scale-in stats, smooth scroll behavior
- **Badge Pulse**: Notification badge pulses when new notifications arrive (2s animation loop)
- **Error Feedback**: Visual feedback (❌ icon) when operations fail, auto-reverts after 2s

### Accessibility Improvements
- **Keyboard Navigation**: All buttons, links, and inputs are fully keyboard accessible
- **Focus States**: Visible 2px outline with 2px offset on all interactive elements
- **Screen Reader Support**: `.sr-only` class for hidden text, semantic HTML structure
- **ARIA Attributes**: Proper ARIA labels on dynamic content (notifications, modals)
- **Reduced Motion**: Respects `prefers-reduced-motion: reduce` media query, disables animations for users
- **Color Contrast**: All text meets WCAG AA standards (4.5:1 minimum for normal text)
- **Focus Trap**: Modals prevent tab navigation outside dialog bounds

### Performance Optimizations
- **will-change CSS Property**: Applied to frequently animated elements (video cards, buttons)
- **Passive Event Listeners**: Scroll events use `{ passive: true }` for better scroll performance
- **Debounced Scroll**: Header shadow effect debounced to prevent excessive repaints
- **Optimized Selectors**: Scoped queries reduce DOM traversal time
- **Minimal Layout Shifts**: CSS prevents cumulative layout shifts (CLS)
- **Lazy Load Images**: Thumbnail images can be loaded on-demand (future enhancement)

### User Experience
- **Empty States**: Friendly messages for no videos, no conversations, no notifications
- **Error Messages**: Clear, actionable error feedback with context (not just "Error")
- **Success Feedback**: Toast-like feedback for successful actions (delete, send, follow)
- **Loading Indicators**: Spinners and loaders for long-running operations
- **Button States**: Disabled state with reduced opacity during submission
- **Form Validation**: Real-time visual feedback on form inputs and errors
- **Conversations**: Direct 1-on-1 messaging with user headers and profile links

### CSS Architecture
- **CSS Variables**: Centralized design tokens (colors, shadows, transitions)
- **Transition System**: 
  - `--transition-fast: 150ms` for quick feedback
  - `--transition-standard: 200ms` for normal interactions
  - `--transition-slow: 300ms` for modal/dropdown animations
- **Keyframe Animations**: Slide-down, fade-in, scale-in, slide-up, slide-in
- **Responsive Design**: 5-breakpoint system (mobile, 576px, 768px, 1024px, 1440px)
- **Dark Mode**: `body.theme-dark` with variable overrides (light-bg, border, text, form inputs); toggle in header via JS

### JavaScript Enhancements
- **Scroll Detection**: Header adds shadow class when scrolled for visual hierarchy
- **Error Handling**: Try-catch blocks with user-friendly error messages
- **Loading State Management**: Visual feedback during async operations
- **Animation Callbacks**: setTimeout-based animation syncing for smooth sequences
- **Performance Monitoring**: Passive event listeners, debounced handlers
- **Graceful Degradation**: Works without JavaScript, enhanced with JS


## Common Tasks

### Add a new controller
1. Create `app/controllers/MyController.php` with class `MyController extends Controller`
2. Add case in `public/index.php`: `case 'myroute': (new MyController())->method(); break;`
3. Use `$this->view('viewname', $data)` to render views

### Add a new model
1. Create `app/models/MyModel.php` with static methods using `Database::getInstance()`
2. Autoloader will find it automatically
3. Follow PDO prepared statement pattern from `Video.php` or `User.php`

### Update styles
1. Edit `public/assets/css/style.css` — uses CSS Grid and CSS variables
2. Use responsive breakpoints: `@media (min-width: 576px)`, `768px`, `1024px`, `1440px`
3. Mobile-first: write base styles for mobile, expand at breakpoints

### Add a new route
1. Add case in `public/index.php` switch statement
2. If route needs URL parameter (e.g. `/watch/123`), extract in index.php before calling controller
3. Access via `$_GET['param']` in controller method

### Clear cache after data changes
```php
Cache::delete('cache_key_name');  // Single key
Cache::clear();                   // All cache
```

## Developer Workflows

Quick local run (PHP built-in server):
```bash
php -S localhost:8000 -t public
```

Ensure `.env` exists with:
```
APP_ENV=development
DB_HOST=localhost
DB_NAME=pandaa
DB_USERNAME=root
DB_PASSWORD=your_password
FFMPEG_PATH=ffmpeg
```

Database setup:
```bash
mysql -u root -p < sql/migrations.sql
```

Check logs in `logs/error.log` for debugging.

## Important Files Reference

- `app/core/App.php` — Bootstrap, autoloading, config loading
- `public/index.php` — Router/dispatcher (switch statement)
- `app/core/Controller.php` — Base controller with `view()`, `redirect()`
- `app/core/Auth.php` — Session, authentication, permission checks
- `app/core/Cache.php` — Redis caching with file fallback
- `app/core/Database.php` — PDO singleton, connection factory
- `app/core/CSRF.php` — CSRF token generation/validation
- `app/core/TwoFA.php` — Two-factor authentication (TOTP)
- `app/config/config.php` — Environment configuration, paths, upload limits
- `sql/migrations.sql` — Database schema with all tables and indexes
- `public/assets/css/style.css` — Main stylesheet (2700+ lines, responsive, mobile-first, evolution polish, empty states, comments, notifications, messages, dark theme)
- `public/assets/js/app.js` — Core JS with micro-interactions, accessibility, performance optimizations
- `app/views/layout/header.php` — Header with navbar, notifications, search
- `app/views/layout/sidebar.php` — Navigation menu with keyboard support
- `app/views/home.php` — Video feed/grid with delete button
- `app/views/watch.php` — Video player, comments, suggestions
- `app/views/profile.php` — User profile with follow/friend request/message buttons; own profile shows Edit section (avatar, username, bio, password) with card-based UI and flash messages
- `app/views/conversation.php` — Direct messaging interface with real-time updates
- `app/controllers/ProfileController.php` — Profile actions (follow, friend requests, messaging)
- `app/controllers/MessagesController.php` — Messaging system with conversation view

## Known Limitations & Future Improvements

- FFmpeg must be installed and accessible on server for video encoding
- Email notifications not yet implemented (use webhooks for production)
- No API rate limiting yet (add when scaling)
- No image optimization on upload (consider WebP conversion for thumbnails)
- Comments support nested replies (parent_id); UI shows reply form per comment
- No full-text search backend (uses basic SQL LIKE queries)
- Dark mode: CSS foundation (`body.theme-dark`) and toggle in place; can be extended
- Service worker (sw.js) not fully implemented (manifest.json present)

If anything is unclear or needs expansion, update this file with the new information. When making evolution-phase changes (polish, a11y, UX), keep **Project Status** and **Recent Improvements** in sync with the current project state.

