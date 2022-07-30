<?php

$namespace = "cooldogedev/MultiProtocol/";
$removeExistingOnes = true;

function main(): void
{
    $start = "/**";
    $end = "*\n * @auto-license\n */";

    $rawLicense = file_get_contents(realpath("../LICENSE"));

    $rawLicense = substr($rawLicense, strpos($rawLicense, "\n") + 1);
    $rawLicense = substr($rawLicense, strpos($rawLicense, "\n") + 1);

    $rawLicense = substr($rawLicense, 0, strrpos($rawLicense, "\n"));

    $rawLicense = implode(array_map(fn(string $line) => (strlen($line) > 1 ? "\n * " : "\n *") . $line, explode("\n", $rawLicense)));

    $license = $start . PHP_EOL . " * " . $rawLicense . " " . $end;

    global $namespace, $removeExistingOnes;

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath("../src/" . $namespace), FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

    foreach ($files as $file) {
        if ($file->isFile()) {
            $content = file_get_contents($file->getPathname());

            // Remove existing license headers that were generated by this script, assuming the license hasn't been changed.
            if ($removeExistingOnes && str_contains($content, "@auto-license")) {
                $content = substr($content, strpos($content, $end) + strlen($end));
                $content = "<?php" . $content;
            }

            $content = preg_replace("/^(declare\(strict_types=1\);)/m", $license . "\n\n$1", $content, 1);

            file_put_contents($file->getPathname(), $content);
        }
    }
}

main();
