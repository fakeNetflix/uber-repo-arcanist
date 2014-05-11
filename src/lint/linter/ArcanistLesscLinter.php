<?php

/**
 * A linter for LESSCSS files.
 *
 * This linter uses [[https://github.com/less/less.js | lessc]] to detect
 * errors and potential problems in [[http://lesscss.org/ | LESS]] code.
 */
final class ArcanistLesscLinter extends ArcanistExternalLinter {

  const LINT_RUNTIME_ERROR   = 1;
  const LINT_ARGUMENT_ERROR  = 2;
  const LINT_FILE_ERROR      = 3;
  const LINT_NAME_ERROR      = 4;
  const LINT_OPERATION_ERROR = 5;
  const LINT_PARSE_ERROR     = 6;
  const LINT_SYNTAX_ERROR    = 7;

  public function getLinterName() {
    return 'LESSC';
  }

  public function getLinterConfigurationName() {
    return 'lessc';
  }

  public function getLinterConfigurationOptions() {
    return parent::getLinterConfigurationOptions() + array(
      'lessc.strict-math' => 'optional bool',
      'lessc.strict-units' => 'optional bool',
    );
  }

  public function getLintNameMap() {
    return array(
      self::LINT_RUNTIME_ERROR   => pht('Runtime Error'),
      self::LINT_ARGUMENT_ERROR  => pht('Argument Error'),
      self::LINT_FILE_ERROR      => pht('File Error'),
      self::LINT_NAME_ERROR      => pht('Name Error'),
      self::LINT_OPERATION_ERROR => pht('Operation Error'),
      self::LINT_PARSE_ERROR     => pht('Parse Error'),
      self::LINT_SYNTAX_ERROR    => pht('Syntax Error'),
    );
  }

  public function getDefaultBinary() {
    return 'lessc';
  }

  public function getCacheVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    if (preg_match('/^lessc (?P<version>\d+\.\d+\.\d+)$/', $stdout, $matches)) {
      $version = $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht('Install lessc using `npm install -g less`.');
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  public function supportsReadDataFromStdin() {
    // Technically `lessc` can read data from standard input however, when doing
    // so, relative imports cannot be resolved. Therefore, this functionality is
    // disabled.
    return false;
  }

  public function getReadDataFromStdinFilename() {
    return '-';
  }

  protected function getMandatoryFlags() {
    return array(
      '--lint',
      '--no-color',
      '--strict-math='.
        ($this->getConfig('lessc.strict-math') ? 'on' : 'off'),
      '--strict-units='.
        ($this->getConfig('lessc.strict-units') ? 'on' : 'off'));
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stderr, false);

    $messages = array();
    foreach ($lines as $line) {
      $matches = null;
      $match = preg_match(
        '/^(?P<name>\w+): (?P<description>.+) ' .
        'in (?P<path>.+|-) ' .
        'on line (?P<line>\d+), column (?P<column>\d+):$/',
        $line,
        $matches);

      if ($match) {
        switch ($matches['name']) {
          case 'RuntimeError':
            $code = self::LINT_RUNTIME_ERROR;
            break;

          case 'ArgumentError':
            $code = self::LINT_ARGUMENT_ERROR;
            break;

          case 'FileError':
            $code = self::LINT_FILE_ERROR;
            break;

          case 'NameError':
            $code = self::LINT_NAME_ERROR;
            break;

          case 'OperationError':
            $code = self::LINT_OPERATION_ERROR;
            break;

          case 'ParseError':
            $code = self::LINT_PARSE_ERROR;
            break;

          case 'SyntaxError':
            $code = self::LINT_SYNTAX_ERROR;
            break;

          default:
            throw new RuntimeException(pht(
              'Unrecognized lint message code "%s".',
              $code));
        }

        $code = $this->getLintCodeFromLinterConfigurationKey($matches['name']);

        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setLine($matches['line']);
        $message->setChar($matches['column']);
        $message->setCode($this->getLintMessageFullCode($code));
        $message->setSeverity($this->getLintMessageSeverity($code));
        $message->setName($this->getLintMessageName($code));
        $message->setDescription(ucfirst($matches['description']));

        $messages[] = $message;
      }
    }

    if ($err && !$messages) {
      return false;
    }

    return $messages;
  }
}