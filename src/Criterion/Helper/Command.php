<?php
namespace Criterion\Helper;

class Command
{
    public $response = null;
    public $command = null;
    public $output = null;

    public function execute($command)
    {
        $this->command = $command;

        ob_start();
        passthru($this->command . ' 2>&1', $response);
        $this->output = ob_get_contents();
        ob_end_clean();

        $this->response = (string) $response;

        $this->output = trim($this->output);
        $this->output = str_replace(TEST_DIR, null, $this->output);

        return $this->response === '0';
    }
}