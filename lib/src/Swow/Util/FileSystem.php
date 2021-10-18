<?php
/**
 * This file is part of Swow
 *
 * @link     https://github.com/swow/swow
 * @contact  twosee <twosee@php.net>
 *
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code
 */

declare(strict_types=1);

namespace Swow\Util;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Swow\Util\FileSystem\IOException;
use function array_filter;
use function array_values;
use function array_walk;
use function copy;
use function error_get_last;
use function is_dir;
use function is_file;
use function is_link;
use function mkdir;
use function rmdir;
use function scandir;
use function unlink;

class FileSystem
{
    public static function scanDir(string $dir, callable $filter = null): array
    {
        $files = @scandir($dir);
        if ($files === null) {
            throw IOException::getLast();
        }
        $files = array_filter($files, function (string $file) { return $file[0] !== '.'; });
        array_walk($files, function (&$file) use ($dir) { $file = "{$dir}/{$file}"; });

        return array_values($filter ? array_filter($files, $filter) : $files);
    }

    public static function remove(string $path, bool $recursive = true): void
    {
        if (!is_dir($path) || is_link($path)) {
            if (@unlink($path) === false) {
                throw IOException::getLast();
            }
            return;
        }

        if (!$recursive) {
            if (@rmdir($path) === false) {
                throw IOException::getLast();
            }
            return;
        }

        $it = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            static::remove($file->getRealPath(), false);
        }
        if (@rmdir($path) === false) {
            throw IOException::getLast();
        }
    }

    /**
     * @param string $source
     * @param string $destination
     * @param bool $ignoreErrors
     * @return array Errors that occurred in the copy() operation
     */
    public static function copy(string $source, string $destination, bool $ignoreErrors = false): array
    {
        if (is_file($source)) {
            if (!@copy($source, $destination)) {
                throw IOException::getLast();
            }
            return [];
        }

        $errors = [];
        if (!is_dir($destination) && !@mkdir($destination, 0755)) {
            throw IOException::getLast();
        }
        $it = new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::SELF_FIRST);
        /** @var $files RecursiveIteratorIterator|RecursiveDirectoryIterator */
        foreach ($files as $info) {
            $ret = true;
            $subPath = $files->getSubPathName();
            if ($info->isDir()) {
                $directory = "{$destination}/{$subPath}";
                /**@var $info RecursiveDirectoryIterator */
                if (!is_dir($directory) && !@mkdir($directory, 0755)) {
                    $ret = false;
                }
            } else {
                /**@var $info SplFileInfo */
                $file = "{$destination}/{$subPath}";
                if (!@copy($info->getPathname(), $file)) {
                    $ret = false;
                }
            }
            if (!$ret) {
                if ($ignoreErrors) {
                    $errors[] = error_get_last();
                } else {
                    throw IOException::getLast();
                }
            }
        }

        return $errors;
    }

    public static function move(string $source, string $destination)
    {
        FileSystem::copy($source, $destination);
        FileSystem::remove($source);
    }
}