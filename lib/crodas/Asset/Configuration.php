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
namespace crodas\Asset;

class Configuration
{
    protected $file;
    protected $data = array();
    protected $loaded = array();

    public function __destruct()
    {
        if ($this->file && $this->data != $this->loaded) {
            @file_put_contents(
                $this->file, 
                '<?php return ' . var_export($this->data, true) . ';',
                LOCK_EX
            );
        }
    }

    public function isProduction()
    {
        $this->data['prod'] = true;
        return $this;
    }

    public function &getData()
    {
        return $this->data;
    }

    public function store($file)
    {
        $this->file   = $file;
        $this->loaded = array_filter((Array)@include $file);
        $this->data   = array_merge($this->loaded, $this->data);

        return $this;
    }

    public function setDir($type, $dir, $local)
    {
        $this->data['dir'][$type] = array('local' => $dir . '/', 'web' => $local . '/');
        return $this;
    }

    public static function get($map = '')
    {
        static $instance = null;
        if (!$instance) {
            $instance = new self($map);
        }
        return $instance;
    }
}
