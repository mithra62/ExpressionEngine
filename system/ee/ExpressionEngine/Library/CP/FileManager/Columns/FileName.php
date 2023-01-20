<?php
/**
 * This source file is part of the open source project
 * ExpressionEngine (https://expressionengine.com)
 *
 * @link      https://expressionengine.com/
 * @copyright Copyright (c) 2003-2023, Packet Tide, LLC (https://www.packettide.com)
 * @license   https://expressionengine.com/license Licensed under Apache License, Version 2.0
 */

namespace ExpressionEngine\Library\CP\FileManager\Columns;

use ExpressionEngine\Library\CP\EntryManager;

/**
 * FileName Column
 */
class FileName extends EntryManager\Columns\Column
{
    public function getTableColumnLabel()
    {
        return 'name';
    }

    public function renderTableCell($data, $field_id, $file)
    {
        return $file->file_name;
    }
}
