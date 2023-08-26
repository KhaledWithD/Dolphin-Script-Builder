<?php

$pharBuilder = new PharBuilder();
$pharBuilder->build('dolphin', 'C:\Coding\Dolphin Script', "src/dolphin.php");

class PharBuilder {

    public function __construct() {
        define("FILE", "Version.php");
        define("LINE", "public const BUILT_VERSION = ");
        define("GIT_LINK", "https://raw.githubusercontent.com/KhaledWithD/Dolphin-Script-Version/main/system_var.json");
        define("SHA_LINK", "https://api.github.com/repos/KhaledWithD/Dolphin-Script-Version/contents/system_var.json");
        define("GIT_TOKEN", "GIT_TOKEN");
    }

    public function build(string $pharName, string $path, string $path4Req) {
        $built = $this->modifyBuilt($path);
        try {
            $bootstrap = $this->getBootstrap($path4Req);
            $phar = new Phar($pharName.".phar", 0);
            $phar->setMetadata(["bootstrap" => $bootstrap]);
            $phar->setStub('<?php require("phar://".__FILE__."/'.$path4Req.'"); __HALT_COMPILER();
            ');
            $phar->startBuffering();

            $directory = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
            $iterator = new \RecursiveIteratorIterator($directory);
            $count = count($phar->buildFromIterator($iterator, $path));
            echo "ID: ".$count."\n";
            $this->updateGit($built);

            $phar->stopBuffering();

        } catch(PharException $e) {
            echo $e->getMessage();
        }
    }

    protected function getBootstrap($path4Req) {
        $bootstrap = explode("/", $path4Req);
        $bootstrap = $bootstrap[count($bootstrap)-1];
        return $bootstrap;
    }

    public function modifyBuilt(string $path) {
        $file_path = $path."/src/".FILE;
        if(!file_exists($file_path)) {
            $from_content = file_get_contents("./Version.php");
            $data = $this->getGitData();
            $dolphin_version = $data["version"]["dolphin_version"];
            $latest_built_version = $data["version"]["built_version"];

            $new_content = preg_replace(["/REPLACE_VERSION/", '/"REPLACE_BUILT"/'], [$dolphin_version, $latest_built_version], $from_content);

            file_put_contents($file_path, $new_content);

            return false;
        }

        $file_content = file_get_contents($file_path);
        $reg = "/".LINE.'(\d+);/';
        preg_match_all($reg, $file_content, $matches);
        if(isset($matches[1])) {
            $current_built = $matches[1][0];
            if(is_int(intval($current_built))) {
                $new_built = $current_built+1;

                // rewrite line
                $new_line = LINE.$new_built.";";
                $new_content = preg_replace($reg, $new_line, $file_content);
                
                file_put_contents($file_path, $new_content);
                return $new_built;
            }
        }
    }

    public function getGitData() : array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, GIT_LINK);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $rawdata = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($rawdata, true);

        return $data;
    }

    public function updateGit(int $new_built, string $version = null) {
        $git_data = $this->getGitData();
        
        // update built version
        $old_built = $git_data["version"]["built_version"];

        $new_git_data = $git_data["version"]["built_version"] = $new_built;
        $json = json_encode($git_data);


        $token = GIT_TOKEN;
        $repoOwner = "KhaledWithD";
        $repoName = "Dolphin-Script-Version";
        $filePath = "system_var.json";
        $sha = $this->getSha();

        $apiUrl = "https://api.github.com/repos/$repoOwner/$repoName/contents/$filePath";

        $headers = [
            "Authorization: token $token",
            "User-Agent: DolphinScriptUpdater"
        ];

        $data = [
            "message" => "Update file",
            "content" => base64_encode($json),
            "sha" => $sha
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        print_r($response);
        curl_close($ch);
    }

    public function getSha() {
        $headers = [
            "Authorization: token ".GIT_TOKEN,
            "User-Agent: DolphinScriptUpdater"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, SHA_LINK);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $rawdata = curl_exec($ch);
        curl_close($ch);


        $json = json_decode($rawdata, true);
        $sha = $json["sha"];

        return $sha;
    }
}
