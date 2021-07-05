# Simple Footnotes

- Contributors: mcaskill, nacin
- Tags: footnotes, endnotes, shortcode, references
- License: MIT
- Requires at least: 2.5
- Tested up to: 5.6
- Stable tag: 3.0.0

A simple way to add footnotes using WordPress shortcodes.

## Description

A simple [_shortcode_](https://codex.wordpress.org/Shortcode) solution for adding elegant and semantic footnotes to your WordPress posts and pages.

The plugin is a fork of [Andrew Nacin's Simple Footnotes](https://wordpress.org/plugins/simple-footnotes/) and provides various enhancements over the original.

### Features

- Updated for the latest versions of WordPress
- Integrated support for TinyMCE (based on [Andrew Patton's extension](https://wordpress.org/plugins/simple-footnotes-editor-button/))
- Localized the plugin
- Added hooks for customizing the rendering of the shortcode
- Added `[reflist]` shortcode for customizing where footnotes are displayed
- Added support for named footnotes, `[ref name="foo"]`, to cite the same source more than once
- Added support for grouped footnotes, `[ref group="baz"]`, to customize marker labels and organize types of footnotes
- Refactored PHP code for PSR-friendliness
- Added support for Composer

### Basic Usage

Citations or notes are inserted inside the `[ref]…[/ref]` shortcode.

**Example #1**<sup id="ref-1">[[1]](#note-1)</sup>

> [WordPress] was released on May 27, 2003, by its founders, Matt Mullenweg`[ref]`_Mullenweg, Matt. "WordPress Now Available". WordPress. Published on May 27, 2003._`[/ref]` and Mike Little,`[ref]`_Changeset #8. Timestamp: 2003-04-21 21:37:11._`[/ref]` as a fork of _b2/cafelog_.

The above example will output:

> [WordPress] was released on May 27, 2003, by its founders, Matt Mullenweg<sup>1</sup> and Mike Little,<sup>2</sup> as a fork of _b2/cafelog_.
> 
> **Notes**
>
> 1. Mullenweg, Matt. "WordPress Now Available". WordPress. Published on May 27, 2003. ↩  
> 2. Changeset #8. Timestamp: 2003-04-21 21:37:11. ↩

The content inside the `[ref]…[/ref]` will show in the reference list. The `[ref]` shortcode can be added anywhere a footnote is needed.

## Requirements

| Prerequisite     | How to check      | How to install |
| ---------------- | ----------------- | -------------- |
| PHP >= 5.5.x     | `php -v`          | [php.net](//php.net/manual/en/install.php) |
| WordPress >= 4.x | `wp core version` | [wordpress.org](https://wordpress.org/download/) |

## Installation

### Via Composer

```
$ composer require mcaskill/wp-simple-footnotes
```

### Via WordPress Admin Panel

1. Download the [latest zip](https://github.com/mcaskill/wp-simple-footnotes/releases/latest) of this repo.
2. In your WordPress admin panel, navigate to _Plugins_ → _Add New_.
3. Click _Upload Plugin_.
4. Upload the zip file that you downloaded.

### Activation

Then activate the plugin via [wp-cli](https://wp-cli.org/commands/plugin/activate/).

```
$ wp plugin activate simple-footnotes
```

Or through the WordPress admin panel (_Plugins_ → _Simple Footnotes_ → _Activate_).

## Advanced Usage

### Using the reference list shortcode<sup id="ref-3">[[2]](#note-2)</sup>

Once any number of footnotes have been added to the content and the post is saved, the plugin takes care of generating and numbering the reference list. By default the list is rendered in your theme, below the content in a section titled "Notes".

This can be overriden by adding `[reflist]` wherever the list is desired in your content. This allows you to customize the section and provide your own title on a per-post basis.

If you paginate your posts, you can optionally move your footnotes below your page links. Look under _Settings_ → _Reading_. Footnotes will still appear as normal for posts that are unpaginated.

**Example #2**<sup id="ref-2">[[1]](#note-1)</sup>

> [WordPress] was released on May 27, 2003, by its founders, Matt Mullenweg`[ref]`_Mullenweg, Matt. "WordPress Now Available". WordPress. Published on May 27, 2003._`[/ref]` and Mike Little,`[ref]`_Changeset #8. Timestamp: 2003-04-21 21:37:11._`[/ref]` as a fork of _b2/cafelog_.
> 
> **References**
> 
> `[reflist]`

The above example will output:

> [WordPress] was released on May 27, 2003, by its founders, Matt Mullenweg<sup>1</sup> and Mike Little,<sup>2</sup> as a fork of _b2/cafelog_.
> 
> **References**
> 
> 1. Mullenweg, Matt. "WordPress Now Available". WordPress. Published on May 27, 2003. ↩  
> 2. Changeset #8. Timestamp: 2003-04-21 21:37:11. ↩

A reference list can be split into columns.

```
[reflist columns="20em"]
```

### Using multiple reference lists<sup id="ref-4">[[2]](#note-2)</sup>

A post can have more than one reference list. Each instance of `[reflist]` will display the notes preceding it.

### Using a footnote more than once<sup id="ref-5">[[2]](#note-2)</sup>

You can cite the same source more than once by using _named_ footnotes. The syntax to define a named footnote is:

```
[ref name="foo"]The quick brown fox jumps over the lazy dog[/ref]
```

To invoke the named footnote:

```
[ref name="foo"]
```

### List-defined references<sup id="ref-6">[[2]](#note-2)</sup>

> The quick brown fox jumps over the lazy dog.`[ref name="LazyDog"]`
> Amazingly few discotheques provide jukeboxes.`[ref name="Jukeboxes"]`
> How razorback-jumping frogs can level six piqued gymnasts.`[ref name="JumpingFrogs"]`
> 
> `[reflist]`  
> `[ref name="Jukeboxes"]`This is the jukeboxes reference.`[/ref]`  
> `[ref name="LazyDog"]`This is the lazy dog reference.`[/ref]`  
> `[ref name="JumpingFrogs"]`This is the jumping frogs reference.`[/ref]`  
> `[/reflist]`

### Embedding references<sup id="ref-7">[[2]](#note-2)</sup>

> The quick brown fox jumps over the lazy dog.`[ref group="nb"]`A footnote.`[ref]`A reference for the footnote.`[/ref]``[/ref]`
> 
> **Notes**
> 
> `[reflist group="nb"]`
> 
> **References**
> 
> `[reflist]`

The above example will output:

> The quick brown fox jumps over the lazy dog.<sup>[nb 1]</sup>
> 
> **Notes**
> 
> 1. A footnote.<sup>[1]</sup> ↩
> 
> **References**
> 
> 1. A reference for the footnote. ↩

### Predefined groups<sup id="ref-8">[[2]](#note-2)</sup>

There are several predefined groups that style the appearance of footnote markers.

| Group name  | Footnote shortcode        | Reference list shortcode | Sample labels                  |
| ------------ | ------------------------ | ------------------------ | ------------------------------ |
| _none_      | `[ref]…[/ref]`            | `[reflist]`              | 1 2 3 4 5 6 7 8 9 10           |
| lower-alpha | `[ref group="la"]…[/ref]` | `[reflist group="la"]`   | a b c d e f g h i j            |
| upper-alpha | `[ref group="ua"]…[/ref]` | `[reflist group="ua"]`   | A B C D E F G H I J            |
| lower-roman | `[ref group="lr"]…[/ref]` | `[reflist group="lr"]`   | i ii iii iv v vi vii viii ix x |
| upper-roman | `[ref group="ur"]…[/ref]` | `[reflist group="ur"]`   | I II III IV V VI VII VIII IX X |
| lower-greek | `[ref group="lg"]…[/ref]` | `[reflist group="lg"]`   | α β γ δ ε ζ η θ ι κ            |

## Changelog

### 1.0.0

* Updated for The Modern Age.

### 0.4.0

* If you paginate your posts, footnote numbering will now remain consistent across pages (e.g., each page does not start with 1)

### 0.3.0

* If you paginate your posts, there's now an option under Settings > Reading that enables you to move the footnotes below your page links. Footnotes will still appear as normal for posts that are unpaginated.

### 0.2.0

* Release.

## Upgrade Notice

### 0.4.0

If you paginate your posts, this version will allow footnote numbering to remain consistent across pages (e.g., each page does not start with 1)

### 0.3.0

 If you paginate your posts, this version enables you to move the footnotes below your page links. Footnotes would still appear as normal for posts that are unpaginated.

## Other Notes

1. <b id="note-1">[[a]](#ref-1) [[b]](#ref-2)</b> Text in examples from "[WordPress](https://en.wikipedia.org/wiki/WordPress)." _Wikipedia: The Free Encyclopedia_. Wikimedia Foundation, Inc. 16 May 2016‎.
2. <b id="note-2">[[a]](#ref-3) [[b]](#ref-4) [[c]](#ref-5) [[d]](#ref-6) [[e]](#ref-7) [[f]](#ref-8)</b> Upcoming features‎.
