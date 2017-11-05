<?php
namespace Consolidation\Comments;

class TestComments extends \PHPUnit_Framework_TestCase
{
    function commentTestData()
    {
        $simple_yaml = <<< 'EOT'
# Header

# Something
some: thing

# Another thing
another: thing

# Footer
EOT;

        $simple_yaml_reordered = <<< 'EOT'
another: thing
some: thing
EOT;

        $simple_yaml_expected = <<< 'EOT'
# Header

# Another thing
another: thing

# Something
some: thing

# Footer
EOT;

        $indented_comments = <<< 'EOT'
# Comments
top:
  # with
  one:
    # indentation
    two:
EOT;

        $indented_without_comments = <<< 'EOT'
top:
  one:
    two:
EOT;

        $travis_yaml_with_comments = <<< 'EOT'
dist: trusty
language: php

# Only test the master branch and SemVer tags.
branches:
  only:
    - master
    - '/^[[:digit:]]+\.[[:digit:]]+\.[[:digit:]]+.*$/'

matrix:
  include:
    -
      php: 7.1
      env: dependencies=highest
    -
      php: 7.0.11
    -
      php: 5.6
      env: dependencies=lowest

# Builds are faster if we do not need to sudo
sudo: false

# Store the composer cache for faster builds
cache:
  directories:
    - $HOME/.composer/cache

# Do highest/lowest testing by installing dependencies per the 'dependencies' setting
before_script:
  - 'if [ -z "$dependencies" ]; then composer install --prefer-dist; fi;'
  - 'if [ "$dependencies" = "lowest" ]; then composer update --prefer-dist --prefer-lowest -n; fi;'
  - 'if [ "$dependencies" = "highest" ]; then composer update --prefer-dist -n; fi;'

script:
  - 'composer -n test'

# Process coveralls
after_success:
  - 'travis_retry php vendor/bin/coveralls -v'
EOT;

      $travis_yaml_without_comments = <<< 'EOT'
dist: trusty
language: php
branches:
  only:
    - master
    - '/^[[:digit:]]+\.[[:digit:]]+\.[[:digit:]]+.*$/'
matrix:
  include:
    -
      php: 7.1
      env: dependencies=highest
    -
      php: 7.0.11
    -
      php: 5.6
      env: dependencies=lowest
sudo: false
cache:
  directories:
    - $HOME/.composer/cache
before_script:
  - 'if [ -z "$dependencies" ]; then composer install --prefer-dist; fi;'
  - 'if [ "$dependencies" = "lowest" ]; then composer update --prefer-dist --prefer-lowest -n; fi;'
  - 'if [ "$dependencies" = "highest" ]; then composer update --prefer-dist -n; fi;'
script:
  - 'composer -n test'
after_success:
  - 'travis_retry php vendor/bin/coveralls -v'
EOT;

        return [
            [ $simple_yaml, $simple_yaml_reordered, $simple_yaml_expected, ],
            [ $indented_comments, $indented_without_comments, $indented_comments, ],
            [ $travis_yaml_with_comments, $travis_yaml_without_comments, $travis_yaml_with_comments ],
        ];
    }

    /**
     * Test CommandInfo command annotation parsing.
     *
     * @dataProvider commentTestData
     */
    function testCommentParsing($original_contents, $altered_contents, $expected)
    {
        // Second step: collect comments from original document and inject them into result.

        $commentManager = new Comments();
        $commentManager->collect(explode("\n", $original_contents));
        $altered_with_comments = $commentManager->inject(explode("\n", $altered_contents));

        $this->assertEquals($expected, implode("\n", $altered_with_comments));
    }
}
