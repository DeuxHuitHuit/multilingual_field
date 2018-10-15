<?php

/**
 *
 */
class EntryQueryMultilingualTextboxAdapter extends EntryQueryTextboxAdapter
{
    public function getHandleFilterColumns()
    {
        $lc = FLang::getLangCode();

        if ($lc) {
            return ["handle-$lc"];
        }

        return parent::getFilterColumns();
    }

    public function getBooleanFilterColumns()
    {
        $lc = FLang::getLangCode();

        if ($lc) {
            return ["value-$lc"];
        }

        return parent::getBooleanFilterColumns();
    }

    public function getFilterColumns()
    {
        $lc = FLang::getLangCode();

        if ($lc) {
            return ["value-$lc", "handle-$lc"];
        }

        return parent::getFilterColumns();
    }

    public function getSortColumns()
    {
        $lc = FLang::getLangCode();

        if ($lc) {
            return ["handle-$lc"];
        }

        return parent::getSortColumns();
    }
}
