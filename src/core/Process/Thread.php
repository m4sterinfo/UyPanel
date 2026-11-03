<?php

/**
 * Envoltorio para procesos
 *
 * Gestión de un proceso hijo vía proc_open().
 * Se usa junto con Multithread para la ejecución paralela.
 *
 * @see Multithread
 */

class Thread {

    /** @var resource|null proc_open handle */
    public $process = null;

    /** @var array Pipes (stdin, stdout, stderr) */
    public $pipes = null;

        /** @var string Buffer interno */
    public $buffer = null;

        /** @var string stdout acumulado */
    public $output = null;

        /** @var string stderr acumulado */
    public $error = null;

        /** @var int Timeout en segundos (0 = sin límites) */
    public $timeout = null;

        /** @var int Hora de arranque del proceso */
    public $start_time = null;

    public function __construct() {
        $this->process = 0;
        $this->buffer = '';
        $this->pipes = (array) null;
        $this->output = '';
        $this->error = '';
        $this->start_time = time();
        $this->timeout = 0;
    }

    /**
          * Crear un proceso nuevo a partir de un comando
     *
          * @param string $command Comando shell
     * @return Thread
     */
    public static function create($command) {
        $t = new Thread();
        $descriptor = array(array('pipe', 'r'), array('pipe', 'w'), array('pipe', 'w'));
        $t->process = proc_open($command, $descriptor, $t->pipes);
        stream_set_blocking($t->pipes[1], 0);
        stream_set_blocking($t->pipes[2], 0);
        return $t;
    }

    /**
          * Comprobar si el proceso está activo
     *
     * @return bool
     */
    public function isActive() {
        $this->buffer .= $this->listen();
        $f = stream_get_meta_data($this->pipes[1]);
        return !$f['eof'];
    }

    /**
          * Cerrar el proceso
     *
     * @return int Exit code
     */
    public function close() {
        $r = proc_close($this->process);
        $this->process = null;
        return $r;
    }

    /**
          * Enviar datos al stdin del proceso
     *
     * @param string $thought
     */
    public function tell($thought) {
        fwrite($this->pipes[0], $thought);
    }

    /**
          * Leer la salida del stdout
     *
     * @return string
     */
    public function listen() {
        $buffer = $this->buffer;
        $this->buffer = '';
        while ($r = fgets($this->pipes[1], 1024)) {
            $buffer .= $r;
            $this->output .= $r;
        }
        return $buffer;
    }

    /**
          * Obtener el estado del proceso
     *
     * @return array
     */
    public function getStatus() {
        return proc_get_status($this->process);
    }

    /**
          * Comprobar si se superó el timeout
     *
     * @return bool
     */
    public function isBusy() {
        return 0 < $this->timeout && $this->start_time + $this->timeout < time();
    }

    /**
          * Leer el stderr
     *
     * @return string
     */
    public function getError() {
        $buffer = '';
        while ($r = fgets($this->pipes[2], 1024)) {
            $buffer .= $r;
        }
        return $buffer;
    }
}
