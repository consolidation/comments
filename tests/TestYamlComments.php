<?php
namespace Consolidation\Comments;

use Symfony\Component\Yaml\Yaml;

class TestYamlComments extends \PHPUnit_Framework_TestCase
{
    function setUp()
    {
        $example_yml_path = __DIR__ . '/templates/example.pantheon.yml';
        $example_yml = file_get_contents($example_yml_path);

        $this->parsed_data = Yaml::parse($example_yml);

        // Collect commnets from source data
        $this->commentManager = new Comments();
        $this->commentManager->collect(explode("\n", $example_yml));
    }

    function testAppendData()
    {
        $data = $this->parsed_data;
        $data['foo'] = ['bar' => 'baz'];
        $expected = <<<'EOT'
#
# pantheon.yml
#
# Control the behavior of a site on https://pantheon.io
#

# API version for pantheon.yml schema
api_version: 1

#
# Quicksilver Workflows allow you to specify scripts to run before or after
# various operations on the Pantheon platform.
#
workflows:
  clear_cache: {  }
  deploy: {  }
foo:
  bar: baz

# Last comment
EOT;
        $this->assertExportedYamlEqual($expected, $data);
    }

    function testPrependData()
    {
        $data['foo'] = ['bar' => 'baz'];
        $data = array_merge($data, $this->parsed_data);

        $expected = <<<'EOT'
#
# pantheon.yml
#
# Control the behavior of a site on https://pantheon.io
#
foo:
  bar: baz

# API version for pantheon.yml schema
api_version: 1

#
# Quicksilver Workflows allow you to specify scripts to run before or after
# various operations on the Pantheon platform.
#
workflows:
  clear_cache: {  }
  deploy: {  }

# Last comment
EOT;
        $this->assertExportedYamlEqual($expected, $data);
    }

    function assertExportedYamlEqual($expected, $data)
    {
        $altered_contents = Yaml::dump($data, PHP_INT_MAX, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        // Inject comments back into altered result
        $altered_with_comments = $this->commentManager->inject(explode("\n", $altered_contents));

        $this->assertEquals(trim($expected), trim(implode("\n", $altered_with_comments)));
    }
}
