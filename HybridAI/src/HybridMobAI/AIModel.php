<?php

namespace HybridMobAI;

class AIModel {
    private $pythonScript = "/path/to/q_learning.py";
    private $modelPath = "/path/to/model.pkl";

    public function chooseAction($state) {
        $command = escapeshellcmd("python3 {$this->pythonScript} choose_action {$this->modelPath} {$state}");
        $action = shell_exec($command);
        return intval($action);
    }

    public function learn($state, $action, $reward, $next_state) {
        $command = escapeshellcmd("python3 {$this->pythonScript} learn {$this->modelPath} {$state} {$action} {$reward} {$next_state}");
        shell_exec($command);
    }
}