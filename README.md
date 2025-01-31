# tuskfish2
A second generation rewrite of Tuskfish CMS along MVVM architectural lines.

Tuskfish is a single user micro CMS. It is designed to provide a minimalist yet capable framework for
publishing different kinds of content. It is suitable for use by individuals and small
organisations. It provides the publishing tools that you need and nothing that you don't.

The project emphasis is on creating the simplest and most lightweight code base possible:
* A small, simple code base is easy to understand and maintain as PHP evolves.
* Security is a lot easier to manage in a small project.
* Avoiding use of external libraries as far as possible to reduce attack surface, maintenance overhead
  and code bloat. External libraries in use are: Boostrap 5, jQuery, Bootstrap-datepicker,
  Bootstrap-fileinput, HTMLPurifier and TinyMCE.

Features include:
* Publish a mixed stream of articles, downloads, images, audio, video, static pages, GPS tracks and collections with one simple form.
* Organise your content with tags, collections and content types.
* Bootstrap-based templates with responsive, mobile-first themes.
* Native PHP template engine; easily create new template sets.
* PHP 8, HTML5 and SQLite3 database.
* Single admin system: There is no user rights management system to worry about. They don't have any.
* SQLite database: There is no database server to worry about.
* Exclusive use of prepared statements with bound values and parameters as protection against SQL injection.
* Minimal public-facing code base: Most of the code lives outside the web root.
* Optional two-factor authentication with Yubikeys (main and backup keys).
* Lightweight core library.

System requirements
* PHP 8.3+
* SQLite3 extension.
* PDO extension.
* pdo_sqlite extension.
* GD2 extension.
* [Optional]: curl extension + a Yubikey hardware token are required if you want to use two-factor Yubikey authentication.
* [Optional]: ImageMagick 6 if you prefer to use that over GD2 for thumbnail generation.
* Apache webserver.

Documentation
* Please visit https://tuskfish.biz for the installation guide, user manual, developer manual and API documentation.
