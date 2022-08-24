# commie2

commie 2.0 is a pastebin script with line commenting support. This was originally forked from [splitbrain/commie](https://github.com/splitbrain/commie) and further improved and expanded upon.

## Features
- Pastes are stored as files on the filesystem, encrypted at rest. (no need to configure a database, or plugins, etc.)
- Every line is commentable with the ability for multiple comments from multiple users.
- Comments support Github Markdown!
- Names and emails are cookied for better UX, but easily changable.
- When you make a comment, the user that made the paste can (optionally) receive an email, with syntax highlighted context of the comment. (see screenshots)
- If the paste is markdown, it will auto generate a preview in html (see screenshots)
- Uses [scrivo/highlight.php](https://github.com/scrivo/highlight.php) which is a port of [highlight.js](https://github.com/highlightjs/highlight.js/) with support for many languages.
- Way simple interface and codebase. When I forked it, it took maybe 5 minutes to figure out what was going on.
- Upgraded to modern archtecture to keep only public things in the public directory.
- Compatible with PHP >=7.0

## Install
To install, simply clone this repo and configure your webserver to point to the `public/` directory. You will also need composer and to run `composer install`. Then move to the configuration section.

## Configuration
This is very easy to configure. There is a `.config_sample.php` file with instructions on how to enable emailing, encryption, and other settings. Copy or rename this file to `.config.php` and you're on your way!

## API
There's no "official" api, but you easily could make a request to the `router.php` file to add a new paste or comment.
### New Paste
```
POST router.php

Request Parameters (all required)
---------
do=save
content=yourpaste
name=Your+name
email=your@email.com
language=php (optional, the autodetect is pretty good)

Response
-----------
{"uid":"youruid"}
```

### New Comment
```
POST router.php

Parameters (all required)
---------
do=saveComment
uid=youruid
line=linenumber
comment=yourcomment
user=Your+name
email=your@email.com

Response
-----------
{
	"uid":"youruid",
	"line":5,
	"comment":"yourcomment",
	"user_name":"Your name",
	"user_email":"your@email.com",
	"time":1661023034, // from php time() command
	"color":"abc123" // color based on name of user
}
```

## Screenshots
#### Home Page
![Home Page](screenshots/home.png)

#### Making a comment
![Making a comment](screenshots/comment-form.png)

#### Viewing comment
![Viewing comment](screenshots/comment.png)

#### Comment via Email
![Comment via Email](screenshots/email.png)

#### Markdown Paste Example
![Markdown Paste Example](screenshots/markdown-example.png)

## Contributing
Throw in an issue and if necessary make a pull request. It's a pretty simple codebase!

### Security
Gasp! There's a security issue! If you find one, let me know. There probably are some improvements that can be made. Pull requests are cool too.

## Tips
- If you are going to enable email, it would probably be best to put some controls in place to the paste board (such as only certain users can access the URL, HTTP Basic Auth, etc)

## Troubleshooting
Got questions? Here's some common ones...

#### My pastes won't save!
Make sure that your `data/` directory is writable by the webserver user (www-user, apache, nobody, nginx, etc). Something like `chmod g+w data/` should do the trick. If it you need to also change the owner, do so with `chown apache:apache data/` or whatever user you need.

#### Ok I did that, but they still won't save!
Do you have SELinux enabled? You can check with `sestatus` as root and see. If you do have it enabled, you need to make sure that the `data/` dir has the correct context permissions which would likely be something like `semanage fcontext -a -t httpd_sys_rw_content_t "/path/to/commit/data(/.*)?"` and then run `restorecon -Rv /path/to/commie/`

## License
[MIT License](LICENSE.md)