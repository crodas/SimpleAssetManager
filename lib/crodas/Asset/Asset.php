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

class Asset
{
    public static function generic($type, Array $paths)
    {
        $data = &Configuration::get()->getData();
        $key  = serialize($paths);

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

        $fpath = implode('.', array_map(function($file) {
            return basename($file);
        }, $paths));
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

        file_put_contents($data['cache'][$key]['real'], $content, LOCK_EX);

        return $data['cache'][$key]['fpath'];
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