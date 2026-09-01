# WordPress.org listing assets

This directory is the Git source of truth for the images published to the
top-level `assets/` directory of the WordPress.org SVN repository. It is not
plugin runtime code and must never enter a distributable ZIP.

`bin/build.sh` excludes this directory. The numbered screenshots are captured
from the exact Free WordPress.org edition, and their positional captions live
under `== Screenshots ==` in `readme.txt`.

Before publishing these files to SVN, set the PNG MIME type:

```sh
svn propset svn:mime-type image/png assets/*.png
```

Specification: <https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/>
