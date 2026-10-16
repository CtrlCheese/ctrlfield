<?php

declare(strict_types=1);

namespace CtrlField\Admin\FieldGroups;

/**
 * Keeps the field group JSON folder private.
 *
 * The folder sits inside the web root (the theme or wp-content). Groups are
 * saved as <key>.json.php starting with `<?php return; ?>`, so on every
 * server that runs WordPress a request for one prints nothing — no
 * configuration needed (FieldGroupRepository). These files add a second
 * layer and cover plain .json files copied in by hand:
 *
 *   .htaccess   Apache and LiteSpeed
 *   web.config  IIS
 *   index.php   no directory listing
 *
 * probe() asks the site for one file and reports whether its contents came
 * back; nginxRule() is the line for an nginx server block, for the rare
 * case the folder cannot be rewritten.
 */
final class JsonFolderGuard
{
    public const TRANSIENT = 'ctrlfield_json_exposure';

    public const PRIVATE = 'private';
    public const EXPOSED = 'exposed';
    public const UNKNOWN = 'unknown';

    /** @var array<string, string> file name => contents */
    public const FILES = [
        '.htaccess'  => "# CtrlField: field group files are read by PHP only, never over the web.\n"
            . "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n",
        'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<!-- CtrlField: field group files are read by PHP only, never over the web. -->\n"
            . "<configuration>\n\t<system.webServer>\n\t\t<security>\n\t\t\t<requestFiltering>\n"
            . "\t\t\t\t<fileExtensions allowUnlisted=\"true\">\n\t\t\t\t\t<add fileExtension=\".json\" allowed=\"false\" />\n\t\t\t\t</fileExtensions>\n"
            . "\t\t\t</requestFiltering>\n\t\t</security>\n\t</system.webServer>\n</configuration>\n",
        'index.php'  => "<?php\n// Silence is golden.\n",
    ];

    /** Are all the protection files in place? */
    public static function isProtected(string $dir): bool
    {
        foreach (array_keys(self::FILES) as $name) {
            if (! is_file($dir . '/' . $name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Writes the missing protection files. A file someone edited is kept.
     *
     * @return bool whether all of them are in place afterwards
     */
    public static function protect(string $dir): bool
    {
        if (! is_dir($dir) || ! is_writable($dir)) {
            return self::isProtected($dir);
        }
        $wrote = false;
        foreach (self::FILES as $name => $contents) {
            $path = $dir . '/' . $name;
            if (! is_file($path) && file_put_contents($path, $contents, LOCK_EX) !== false) {
                $wrote = true;
            }
        }
        if ($wrote && function_exists('delete_transient')) {
            delete_transient(self::TRANSIENT); // check again with the new files
        }

        return self::isProtected($dir);
    }

    /**
     * The public URL of a folder, from the first base path it is inside of.
     *
     * @param array<string, string> $bases path => URL, most specific first
     */
    public static function urlFor(string $dir, array $bases): ?string
    {
        $dir = str_replace('\\', '/', rtrim($dir, '/\\'));
        foreach ($bases as $path => $url) {
            $path = str_replace('\\', '/', rtrim($path, '/\\'));
            if ($path !== '' && ($dir === $path || str_starts_with($dir, $path . '/'))) {
                return rtrim($url, '/') . substr($dir, strlen($path));
            }
        }

        return null;
    }

    /** The nginx rule that denies the folder, for the server block. */
    public static function nginxRule(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '/');

        return 'location ^~ ' . rtrim($path, '/') . '/ { deny all; }';
    }

    /**
     * Can a field group file be downloaded? Asks the site itself for one
     * file; the answer is cached (an hour while exposed, a day otherwise).
     *
     * @return array{status: string, url: string}
     */
    public static function probe(string $dir): array
    {
        $files = is_dir($dir) ? array_merge(glob($dir . '/*.json') ?: [], glob($dir . '/*' . FieldGroupRepository::EXTENSION) ?: []) : false;
        $url   = self::urlFor($dir, self::bases());
        if ($files === false || $files === [] || $url === null) {
            return ['status' => self::UNKNOWN, 'url' => (string) $url]; // nothing to expose, or outside the web root
        }

        $cached = get_transient(self::TRANSIENT);
        if (is_array($cached) && ($cached['dir'] ?? '') === $dir && isset($cached['status'], $cached['url'])) {
            return ['status' => (string) $cached['status'], 'url' => (string) $cached['url']];
        }

        $response = wp_remote_get($url . '/' . rawurlencode(basename($files[0])), [
            'timeout'     => 5,
            'redirection' => 0,
            'sslverify'   => false, // a loopback request; local sites often use self-signed certificates
        ]);
        if (is_wp_error($response)) {
            $status = self::UNKNOWN;
        } else {
            // The JSON, or the PHP source when the server does not run .php files.
            $body   = (string) wp_remote_retrieve_body($response);
            $status = (int) wp_remote_retrieve_response_code($response) === 200 && (str_contains($body, '"key"') || str_contains($body, '<?php'))
                ? self::EXPOSED
                : self::PRIVATE;
        }

        set_transient(self::TRANSIENT, ['dir' => $dir, 'status' => $status, 'url' => $url], $status === self::EXPOSED ? HOUR_IN_SECONDS : DAY_IN_SECONDS);

        return ['status' => $status, 'url' => $url];
    }

    /** @return array<string, string> */
    private static function bases(): array
    {
        $bases = [
            get_stylesheet_directory() => get_stylesheet_directory_uri(),
            get_template_directory()   => get_template_directory_uri(),
            WP_CONTENT_DIR             => content_url(),
            ABSPATH                    => site_url(),
        ];

        return $bases;
    }
}
