<?php
/*
  +---------------------------------------------------------------------------------+
  | Copyright (c) 2014 César Rodas                                                  |
  +---------------------------------------------------------------------------------+
  | Redistribution and use in source and binary forms, with or without              |
  | modification, are permitted provided that the following conditions are met:     |
  | 1. Redistributions of source code must retain the above copyright               |
  |    notice, this list of conditions and the following disclaimer.                |
  |                                                                                 |
  | 2. Redistributions in binary form must reproduce the above copyright            |
  |    notice, this list of conditions and the following disclaimer in the          |
  |    documentation and/or other materials provided with the distribution.         |
  |                                                                                 |
  | 3. All advertising materials mentioning features or use of this software        |
  |    must display the following acknowledgement:                                  |
  |    This product includes software developed by César D. Rodas.                  |
  |                                                                                 |
  | 4. Neither the name of the César D. Rodas nor the                               |
  |    names of its contributors may be used to endorse or promote products         |
  |    derived from this software without specific prior written permission.        |
  |                                                                                 |
  | THIS SOFTWARE IS PROVIDED BY CÉSAR D. RODAS ''AS IS'' AND ANY                   |
  | EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED       |
  | WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE          |
  | DISCLAIMED. IN NO EVENT SHALL CÉSAR D. RODAS BE LIABLE FOR ANY                  |
  | DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES      |
  | (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;    |
  | LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND     |
  | ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT      |
  | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS   |
  | SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE                     |
  +---------------------------------------------------------------------------------+
  | Authors: César Rodas <crodas@php.net>                                           |
  +---------------------------------------------------------------------------------+
*/

use crodas\Asset\Configuration;

use crodas\FileUtil\Cache;
use crodas\FileUtil\File;
use ServiceProvider\EventEmitter;

class Asset
{
    use EventEmitter;

    protected static $paths = array();
    protected static $is_prod = false;

    public static function get()
    {
        return new Cache('assets.php', new self);
    }

    public static function prod()
    {
        self::$is_prod = true;
    }

    public static function addPath($path)
    {
        self::$paths[] = $path;
    }

    public static function prepare($path, Array $files, $out)
    {
        $content = "";
        $paths   = array_merge(self::$paths, [$path]);
        foreach ($files as $file) {
            if (!is_file($file)) {
                foreach ($paths as $path) {
                    if (is_file($path . "/" . $file)) {
                        $file = $path . "/" . $file;
                        break;
                    }
                }
            }
            
            self::trigger('file', [$file]);

            $content .= file_get_contents($file);
        }

        $hash = substr(sha1($content), 0, 8);
        $type = strstr($out, ".");

        if (self::$is_prod) {
            if ($type == '.js') {
                $js = new JSqueeze;
                $content = $js->squeeze($content, true);
            } else if ($type == '.css') {
                $content = CssMin::minify($content);
            }
            $hash .= ".min";
        }

        $out = preg_replace("/$type$/", ".{$hash}{$type}", $out);

        foreach ($paths as $path) {
            try {
                File::write($path . '/' . $out, $content);
                self::trigger('file', [$path . '/' . $out]);
                self::trigger('file:output', [$path . '/' . $out]);
                self::trigger('output', [&$out]);
                break;
            } catch (\Exception $e) {
            }
        }

        return $out;
    }

    public static function generic($type, Array $paths)
    {
        static $data;
        if (!$data) {
            $data = &Configuration::get()->getData();
        }
        $key  = serialize($paths);

        if (count($paths) == 1 && !empty($data['packages'][$paths[0]])) {
            $key   = $paths[0];
            $paths = $data['packages'][$paths[0]];
            $fpath = $key;
        }

        if (!empty($data['cache'][$key])) {
            if (!empty($data['prod'])) {
                /* production and built */
                return $data['cache'][$key]['fpath'];
            } 

            $valid = true;
            foreach ($data['cache'][$key]['times'] as $file => $ts) {
                if (!is_file($file) || filemtime($file) > $ts) {
                    $valid = false;
                    break;
                }
            }

            if ($valid && is_file($data['cache'][$key]['real'])) {
                return $data['cache'][$key]['fpath'];
            }
            unlink($data['cache'][$key]['real']);
        }

        $times = array();
        if (!empty($data['dir'][$type])) {
            foreach ($paths as $id => $path) {
                if (is_file($data['dir'][$type]['local'] . $path)) {
                    $paths[$id] = $data['dir'][$type]['local'] . $path;
                }
            }
        }

        if (empty($fpath)) {
            $fpath = implode('.', array_map(function($file) {
                return basename($file);
            }, $paths));
        }
        $fpath = str_replace(".$type", "", $fpath);

        $content = "";
        foreach ($paths as $file) {
            $times[$file] = filemtime($file);
            $content .= file_get_contents($file);
        }

        $stage  = !empty($data['prod']) ? 'prod' : 'dev';
        $fpath .= "." . substr(sha1($content), 0, 8) . ".$stage.$type";

        if (!empty($data['prod'])) {
            if ($type == 'js') {
                $js = new JSqueeze;
                $content = $js->squeeze($content, true);
            } else if ($type == 'css') {
                $content = CssMin::minify($content);
            }
        }
        
        $data['cache'][$key] = array(
            'real'  => $data['dir'][$type]['local'] . $fpath,
            'fpath' => $data['dir'][$type]['web'] . $fpath,
            'times' => $times,
        );

        File::write($data['cache'][$key]['real'], $content);

        return $data['cache'][$key]['fpath'];
    }

    public static function define($name, $paths)
    {
        static $data;
        if (!$data) {
            $data = &Configuration::get()->getData();
        }
        $data['packages'][$name] = (Array)$paths;
    }

    public static function js()
    {
        return self::generic('js', func_get_args());
    }

    public static function css()
    {
        return self::generic('css', func_get_args());
    }
}
