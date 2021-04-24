<?php

namespace D9ify\Site;

use Silvanite\ComposerReader\Composer;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Directory
 *
 * @package D9ify\Site
 */
class Directory {

  /**
   * @var \D9ify\Site\Info
   */
  protected $info;

  /**
   * @var \SplFileInfo
   */
  protected $clonePath;

  /**
   * @var
   */
  protected $composerFile;

  /**
   * Directory constructor.
   *
   * @param \D9ify\Site\Info $site
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @throws \JsonException
   */
  public function __construct(Info $site, OutputInterface $output) {
    $this->info = $site;
    $this->clonePath = new \SplFileInfo(getcwd() . "/" . $this->info->getName());
    if (!$this->clonePath->isDir()) {
      // -oStrictHostKeyChecking=no
      $output->writeln("Destination site %s does not exist... cloning...");
      $command = sprintf("terminus connection:info %s.dev --format=json", $this->info->getName());
      exec($command, $output, $status);
      if ($status !== 0) {
        throw new \Exception("Cannot get command to clone site. " . join(PHP_EOL, $output));
      }
      $connectionInfo = json_decode(join("", $output),true, 5, JSON_THROW_ON_ERROR);
      exec($connectionInfo['git_command'] . " -oStrictHostKeyChecking=no", $output, $status);
      if ($status !== 0)
        throw new \Exception("Cannot clone site with terminus command." . join(PHP_EOL, $output));
    }
    $output->writeln(sprintf("Site Code Folder: %s", $this->clonePath->getRealPath()));
    $this->setComposerFile();
  }


  /**
   * @param \D9ify\Site\Info $site
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return static
   * @throws \JsonException
   */
  public static function ensure(Info $site, OutputInterface $output) {
    return new static($site, $output);
  }

  /**
   * @throws \Exception
   */
  public function setComposerFile() {
    $this->composerFile = new ComposerFile(sprintf("%/%s/composer.json", getcwd(), $this->info->getName()));
  }

  /**
   * @return \D9ify\Site\ComposerFile
   */
  public function getComposerObject(): ComposerFile {
    return $this->composerFile;
  }

  /**
   * @param $site_id
   *
   * @return mixed
   * @throws \JsonException
   */
  function getComposerFileAsArray($site_id) {
    $composerFile = $this->getComposerFile();
    return json_decode($composerFile->valid() ?
      file_get_contents($composerFile->getRealPath()) : "{}", TRUE, 512, JSON_THROW_ON_ERROR);
  }


  /**
   * @param $done
   * @param $total
   */
  function progressBar($done, $total) {
    $perc = floor(($done / $total) * 100);
    $left = 100 - $perc;
    $write = sprintf("\033[0G\033[2K[%'={$perc}s>%-{$left}s] - $perc%% - $done/$total", "", "");
    fwrite(STDERR, $write);
  }

  /**
   * @param $regex
   *
   * @return array
   */
  function spelunkFilesFromRegex($regex) {
    $allFiles = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->clonePath)));
    $max = count($allFiles);
    $current = 0;
    return array_filter($allFiles, function(\SPLFileInfo $file) use ($regex, &$max, &$current) {
      progressBar($current++, $max);
      return preg_match($regex, $file->getFilename()) && !strpos($file->getFilename(), 'test');
    });
  }

  /**
   * @return \D9ify\Site\Info
   */
  public function getSiteInfo(): Info {
    return $this->info;
  }

  /**
   * @param \D9ify\Site\Info $info
   */
  public function setSiteInfo($site_id): void {
    $this->info = new Info($site_id);
  }



}
