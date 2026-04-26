# pdromita/list

Simple PHP daily list manager with password-protected access, text file upload, online creation, and inline editing.

## Features

- password-protected access via `.env`
- create lists directly from the browser
- edit existing lists online
- upload `.txt` lists
- delete lists from the UI

## Requirements

- PHP 8.x
- Apache or Nginx
- write permissions on `uploads/`

## Setup

```bash
cp .env.example .env
```

Set your password in `.env`:

```env
PASSWORD=YourSecurePassword
```

## Run locally

```bash
php -S localhost:8080
```

Then open `http://localhost:8080/list/` if served from the web root, or `http://localhost:8080/` if serving from inside this folder.

## Repository contents

- `index.php` main application
- `.env.example` environment template
- `uploads/` stored list files, ignored by Git