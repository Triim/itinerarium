# Itinerarium

Itinerarium is a travel-planning web application. Visitors browse Cities, Places and author-created Itineraries, each Itinerary being an ordered list of stops in one City. Places can be explored by theme (landmarks, architecture, history, local food, film and TV locations and more), and many show a licensed photograph with its attribution.

Registered users create their own Itineraries, choose and order the stops, and submit them for review. An administrator manages Cities and Places and approves submitted Itineraries, which then become public.

## Technology Stack

- **Laravel 12** (PHP) for routing, database access, validation and server-side logic
- **Inertia.js** to connect Laravel controllers to the React pages
- **React 19** with **TypeScript** for the user interface
- **Tailwind CSS 4** for styling
- **SQLite** as the database
- **Spatie Laravel MediaLibrary** for Place photographs and their resized versions
- **Vite** for frontend development and builds
- **PHPUnit** for automated tests
- **GitHub Actions** for continuous integration

## Requirements

- **PHP 8.4.1 or later**, as required in `composer.json` and by the locked dependencies.
- PHP extensions: `ctype`, `dom`, `exif`, `fileinfo`, `gd`, `iconv`, `mbstring`, `pdo_sqlite`, `sqlite3`, `xml`, `zip`. Run `php -m` to check which are installed. `gd` is used to resize the photographs.
- **Composer 2**
- **Node.js 22** with npm. CI uses this version.
- Git

No database server is needed: SQLite stores everything in a single file.

## Installation

The commands below set up a fresh clone for local development.

```bash
# 1. Clone the repository
git clone https://github.com/Triim/itinerarium.git
cd itinerarium

# 2. Install PHP and JavaScript dependencies
composer install
npm ci

# 3. Create the environment file and the application key
cp .env.example .env
php artisan key:generate

# 4. Create the SQLite database file, then create the tables and load the demo data
touch database/database.sqlite
php artisan migrate --seed

# 5. Make uploaded and seeded photographs reachable from the browser
php artisan storage:link
```

`npm ci` installs exactly the versions in `package-lock.json`. Seeding takes a few seconds, because it imports and resizes the bundled photographs.

### Set the application URL

`.env.example` sets `APP_URL=http://itinerarium.test`, the address Laravel Herd or Valet serves. When you use the built-in PHP server instead, you can set it to match:

```dotenv
APP_URL=http://127.0.0.1:8000
```

Pages and photographs work at either address, because photograph URLs are relative (`/storage/...`). `APP_URL` is used for links generated outside a browser request, such as in console commands.

### Run the application

Start the Laravel server and the Vite development server in two separate terminals:

```bash
php artisan serve      # terminal 1: http://127.0.0.1:8000
npm run dev            # terminal 2: serves the frontend with hot reload
```

Then open <http://127.0.0.1:8000>.

`composer run dev` does the same in one terminal. It also starts a queue worker and a log viewer.

To use compiled assets instead of the Vite development server, run `npm run build` once and only start `php artisan serve`. The compiled files go to `public/build`. If a `public/hot` file is left over from a previous `npm run dev`, delete it.

### Resetting the database

`php artisan migrate:fresh --seed` **deletes every table and all data**, including accounts and Itineraries you created, and then reloads the demo data. Use it only when you want to start over. Photograph files already in `storage/app/public` are not removed by this command.

## Demo Accounts

The seeder creates two accounts. Both use the password `password`.

| Role          | Email               | Password   |
| ------------- | ------------------- | ---------- |
| Administrator | `admin@example.com` | `password` |
| Regular user  | `test@example.com`  | `password` |

These credentials are for local demonstration only and must not be used on a public server. Visitors can also create a regular account at `/register`.

## Demo Data

A fresh `php artisan migrate --seed` creates:

- 8 Cities: Barcelona, Bangkok, Madrid, Berlin, Paris, Brussels, Bruges and Hamburg
- 118 Places, with descriptions, coordinates, themes and film references where relevant
- 10 published Itineraries: 7 real routes and 3 short samples whose titles start with "Sample:"
- 120 ordered Itinerary stops
- 97 licensed Place photographs, each with its author, source and licence, plus a thumbnail and a detail image of each

The real routes, Places and photograph credits are read from `database/data/real-travel/`. The photographs are bundled in `images.tar` in the same folder, so seeding needs no download.

## Using the Application

**Visitors** can browse published Itineraries, Cities and Places. They can filter Places by City and by theme, and see each Place's photograph credit, film references and the published Itineraries that include it. Drafts and Itineraries awaiting review are not public.

**Registered users** have a dashboard and a "My Itineraries" area (`/user/itineraries`). There they can:

- create an Itinerary for a City, then select its Places and put them in order
- edit or delete their own Itineraries
- submit a draft for review. If a pending Itinerary is edited, it goes back to draft and must be submitted again. Editing a published Itinerary keeps it published.

Regular users cannot manage Cities or Places, cannot see other users' Itineraries in the management area, and cannot approve Itineraries.

**Administrators** can also:

- create, edit and delete Cities. Only a City with no Places or Itineraries can be deleted.
- create, edit and delete Places, including uploading a JPEG or PNG photograph
- see and manage every user's Itineraries, and approve those awaiting review

## Email Notifications

When an administrator approves an Itinerary, its author receives an email saying it has been published.

The default configuration (`MAIL_MAILER=log`) does not send real email. The full message is written to `storage/logs/laravel.log`. To deliver real email, set the `MAIL_*` values in `.env` for an SMTP server; this has not been configured or tested for this project.

## Updating Photographs on an Existing Installation

A fresh installation already has the current photographs after seeding, so this section can be skipped.

An older installation that was seeded with a previous set of bundled images can be updated without reseeding:

```bash
php artisan real-travel:sync-images --dry-run   # show what would change, change nothing
php artisan real-travel:sync-images             # apply after confirming the prompt
php artisan real-travel:sync-images --force     # apply without the prompt
```

Back up `database/database.sqlite` and `storage/app/public` first. The command only replaces or removes images that came from a bundle. Photographs uploaded through the application are listed as "keep" and left untouched.

## Running Tests

```bash
php artisan test          # Laravel test suite (PHPUnit)
npx eslint .              # frontend lint check (npm run lint applies automatic fixes instead)
npm run format:check      # Prettier check (npm run format rewrites files instead)
npm run build             # production frontend build
```

The tests use an in-memory SQLite database (configured in `phpunit.xml`), so they never touch `database/database.sqlite`. Some page tests need the Vite manifest, so run `npm run build` before `php artisan test` on a fresh clone. The full suite takes about a minute.

TypeScript type checking (`npx tsc --noEmit`) passes, but is not yet part of the CI workflow.

The same lint, formatting, build and test steps run on GitHub Actions for every push and pull request to `main`. Results are on the repository's [Actions page](https://github.com/Triim/itinerarium/actions). The workflow is in `.github/workflows/tests.yml`.

## Project Structure

Laravel handles routing, database access, validation and authorization. Each controller returns an Inertia response, which a React page in `resources/js/pages` renders in the browser. The project has no Blade views except the single root template.

| Path                        | Contents                                                                                     |
| --------------------------- | -------------------------------------------------------------------------------------------- |
| `app/Models`                | Eloquent models: `User`, `City`, `Place`, `Itinerary`                                        |
| `app/Http/Controllers`      | Public pages at the top level; the management area under `Userzone/`                          |
| `routes/web.php`            | Public, authenticated and administrator routes                                                |
| `resources/js/pages`        | React (TypeScript) pages rendered through Inertia                                             |
| `resources/js/components`   | Shared React components, such as forms, cards, navigation and UI primitives                   |
| `database/migrations`       | Database schema                                                                               |
| `database/seeders`          | Demo accounts, sample data and the real-travel importer                                       |
| `database/data/real-travel` | Real routes, Places, photograph credits (`*.json`) and the bundled photographs (`images.tar`) |
| `tests`                     | PHPUnit feature and unit tests                                                                |
