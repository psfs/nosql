#!/bin/sh
set -eu

xdebug_version="3.5.3"
mongodb_version="2.5.3"
source_dir="$(mktemp -d)"
trap 'rm -rf "$source_dir"' EXIT HUP INT TERM

php_ini="$(php -r '$file = php_ini_loaded_file(); if ($file === false) { exit(1); } echo $file;')"
if [ ! -w "$php_ini" ]; then
    echo "PHP ini file is not writable: $php_ini" >&2
    exit 1
fi

if ! php -r 'exit(extension_loaded("mongodb") && version_compare(phpversion("mongodb"), "2.5.3", ">=") ? 0 : 1);'; then
    curl --fail --silent --show-error --location \
        "https://pecl.php.net/get/mongodb-${mongodb_version}.tgz" \
        --output "$source_dir/mongodb.tgz"
    tar -xzf "$source_dir/mongodb.tgz" -C "$source_dir"
    cd "$source_dir/mongodb-${mongodb_version}"
    phpize
    ./configure --with-php-config="$(command -v php-config)"
    make -j2
    make install
    mongodb_ini="extension=$(php-config --extension-dir)/mongodb.so"
    grep -Fqx "$mongodb_ini" "$php_ini" || printf '\n%s\n' "$mongodb_ini" >> "$php_ini"
fi

if ! php -r 'exit(extension_loaded("xdebug") ? 0 : 1);'; then
    curl --fail --silent --show-error --location \
        "https://pecl.php.net/get/xdebug-${xdebug_version}.tgz" \
        --output "$source_dir/xdebug.tgz"
    tar -xzf "$source_dir/xdebug.tgz" -C "$source_dir"
    cd "$source_dir/xdebug-${xdebug_version}"
    phpize
    ./configure --enable-xdebug --with-php-config="$(command -v php-config)"
    make -j2
    make install
    xdebug_ini="zend_extension=$(php-config --extension-dir)/xdebug.so"
    grep -Fqx "$xdebug_ini" "$php_ini" || printf '\n%s\n' "$xdebug_ini" >> "$php_ini"
fi

xdebug_mode_ini="xdebug.mode=coverage"
grep -Fqx "$xdebug_mode_ini" "$php_ini" || printf '\n%s\n' "$xdebug_mode_ini" >> "$php_ini"
php -r 'if (!extension_loaded("mongodb") || version_compare(phpversion("mongodb"), "2.5.3", "<")) { fwrite(STDERR, "MongoDB extension 2.5.3 or later is not loaded\n"); exit(1); } echo "MongoDB ", phpversion("mongodb"), " loaded\n";'
php -r 'if (!extension_loaded("xdebug") || ini_get("xdebug.mode") !== "coverage") { fwrite(STDERR, "Xdebug coverage is not enabled\n"); exit(1); } echo "Xdebug ", phpversion("xdebug"), " with coverage enabled\n";'
