<?php

namespace Consolidation\Comments;

/**
 * Remember comments in one text file (usually a yaml file), and
 * re-inject them into an edited copy of the same file.
 *
 * This is a workaround for the fact that the Symfony Yaml parser
 * does not record comments.
 *
 * Comments at the beginning and end of the file are guarenteed to
 * be retained at the beginning and the end of the resulting file.
 *
 * Comments that appear before sections of yaml that is deleted will
 * be deliberately discarded as well.
 *
 * If the resulting yaml file contents are reordered, comments may
 * become mis-ordered (attached to the wrong element).
 *
 * Comments that appear before sections of yaml that are edited may
 * be inadvertantly lost. It is recommended to always place comments
 * immediately before identifier lines (i.e. "foo:").
 */
class Comments
{
    protected $hasStored;
    protected $headComments;
    protected $accumulated;
    protected $lineIds;
    protected $stored;
    protected $endComments;

    public function __construct()
    {
        $this->hasStored = false;
        $this->headComments = false;
        $this->accumulated = [];
        $this->lineIds = [];
        $this->stored = [];
        $this->endComments = [];
    }

    /**
     * Collect all of the comments from a text file
     * (usually a yaml file).
     *
     * @param array $contentLines
     */
    public function collect(array $contentLines)
    {
        $contentLines = $this->removeTrailingBlankLines($contentLines);

        // Put look through the rest of the lines and store the comments as needed
        foreach ($contentLines as $line) {
            if ($this->isBlank($line)) {
                $this->accumulateEmptyLine();
            } elseif ($this->isComment($line)) {
                $this->accumulate($line);
            } else {
                $this->storeAccumulated($line);
            }
        }
        $this->endCollect();
    }

    /**
     * Description
     * @param array $contentLines
     * @return array of lines with comments re-intersperced
     */
    public function inject(array $contentLines)
    {
        $contentLines = $this->removeTrailingBlankLines($contentLines);

        // If there were any comments at the beginning of the
        // file, then put them back at the beginning.
        $result = $this->headComments === false ? [] : $this->headComments;
        foreach ($contentLines as $line) {
            $fetched = $this->find($line);
            $result = array_merge($result, $fetched);

            $result[] = $line;
        }
        // Any comments found at the end of the file will stay at
        // the end of the file.
        $result = array_merge($result, $this->endComments);
        return $result;
    }

    /**
     * @param string $line
     * @return true if provided line is a comment
     */
    protected function isComment($line)
    {
        return preg_match('%^ *#%', $line);
    }

    /**
     * Stop collecting. Any accumulated comments will be
     * remembered so that they may be re-injected at the
     * end of the new file.
     */
    protected function endCollect()
    {
        $this->endComments = $this->accumulated;
        $this->accumulated = [];
    }

    protected function accumulateEmptyLine()
    {
        if ($this->hasStored) {
            return $this->accumulate('');
        }

        if ($this->headComments === false) {
            $this->headComments = [];
        }

        $this->headComments = array_merge($this->headComments, $this->accumulated);
        $this->accumulated = [''];
    }

    /**
     * Accumulate comments and blank lines in our cache.
     * @param string $line
     */
    protected function accumulate($line)
    {
        $this->accumulated[] = $line;
    }

    /**
     * When a non-comment line is found, remember all of
     * the comment lines that came before it in our cache.
     *
     * @param string $line
     */
    protected function storeAccumulated($line)
    {

        // Remember that we called storeAccumulated at least once
        $this->hasStored = true;

        // The very first time storeAccumulated is called, the
        // accumulated comments will be placed in $this->headComments
        // instead of stored, so they may be restored to the
        // beginning of the new file.
        if ($this->headComments === false) {
            $this->headComments = $this->accumulated;
            $this->accumulated = [];
            return;
        }
        if (!empty($this->accumulated)) {
            $lineId = $this->getLineId($line, true);
            $this->stored[$lineId][] = $this->accumulated;
            $this->accumulated = [];
        }
    }

    /**
     * Generates unique line id based on the key it contains. It allows
     * to reattach comments to the edited yaml sections.
     *
     * For example, lets take a look at the following yaml:
     *
     * # Top comments
     * top:
     * # Top one
     *   one:
     *     # Top two
     *     two: two
     * # Bottom comments
     * bottom:
     *   # Bottom one
     *   one:
     *     # Bottom two
     *     two: 2
     *
     * This method generates ids based on keys (discarding values).
     * Additionally, duplicating keys are taken into account as well.
     * The following ids will be generated:
     *
     * 1|top
     * 1|  one
     * 1|    two
     * 1|bottom
     * 2|  one
     * 2|    two
     *
     * @param string $line
     * @param bool $isCollecting
     */
    protected function getLineId($line, $isCollecting = true)
    {
        list($id) = explode(':', $line, 2);

        if ($isCollecting) {
            if (isset($this->lineIds[$id])) {
                $this->lineIds[$id][] = end($this->lineIds[$id]) + 1;
            } else {
                $this->lineIds[$id] = [1];
            }

            return end($this->lineIds[$id]) . '|' . $id;
        }

        if (isset($this->lineIds[$id])) {
            return array_shift($this->lineIds[$id]) . '|' . $id;
        } else {
            return  '1|' . $id;
        }
    }

    /**
     * Check to see if the provided line has any associated comments.
     *
     * @param string $line
     */
    protected function find($line)
    {
        $lineId = $this->getLineId($line, false);
        if (!isset($this->stored[$lineId]) || empty($this->stored[$lineId])) {
            return [];
        }
        // The stored result is a stack of accumulated comments. Pop
        // one off; if more remain, they will be attached to the next
        // line with the same value.
        return array_shift($this->stored[$lineId]);
    }

    /**
     * Remove all of the blank lines from the end of an array of lines.
     */
    protected function removeTrailingBlankLines($lines)
    {
        // Remove all of the trailing blank lines.
        while (!empty($lines) && $this->isBlank(end($lines))) {
            array_pop($lines);
        }
        return $lines;
    }

    /**
     * Return 'true' if the provided line is empty (save for whitespace)
     */
    protected function isBlank($line)
    {
        return preg_match('#^\s*$#', $line);
    }
}
