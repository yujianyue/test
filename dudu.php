<?php
header('Content-Type: text/html; charset=utf-8');
$puzzleInput = isset($_GET['p']) ? $_GET['p'] : (isset($_POST['p']) ? $_POST['p'] : '');
if (empty($puzzleInput)) {
    $puzzleInput = "530070000600195000098000060800060003400803001700020006060000280000419005000080079";
}
function parsePuzzle($text) {
    $cleaned = preg_replace('/[^0-9.]/', '', $text);
    $values = [];
    for ($i = 0; $i < 81 && $i < strlen($cleaned); $i++) {
        $ch = $cleaned[$i];
        $values[] = ($ch === '.' || $ch === '0') ? 0 : intval($ch);
    }
    while (count($values) < 81) {
        $values[] = 0;
    }
    return $values;
}
$tech = [
    'NakedSingle' => '显性单数 (Naked Single)',
    'HiddenSingle' => '隐性单数 (Hidden Single)',
    'Claiming' => '占位排除 (Claiming)',
    'Pointing' => '定向排除 (Pointing)',
    'NakedSubset' => '显性子集 (Naked Subset)',
    'HiddenSubset' => '隐性子集 (Hidden Subset)',
    'X_Wing' => 'X 翼 (X-Wing)',
    'XY_Wing' => 'XY 翼 (XY-Wing)',
    'W_Wing' => 'W 翼 (W-Wing)',
    'XYZ_Wing' => 'XYZ 翼 (XYZ-Wing)',
    'X_Chains' => 'X 链 (X-Chains)',
    'XY_Chains' => 'XY 链 (XY-Chains)',
    'Swordfish' => '剑鱼 (Swordfish)',
    'Skyscraper' => '摩天楼 (Skyscraper)',
    'UniqueRectangle' => '唯一矩形 (Unique Rectangle)'
];
class SudokuSolver {
    private $puzzle;
    private $originalPuzzle;
    private $candidates;
    private $tCOL;
    private $tROW;
    private $tBOX;
    private $steps;
    private $hs_ns = "2";
    public function __construct($puzzleString) {
        $this->puzzle = $this->parsePuzzle($puzzleString);
        $this->originalPuzzle = $this->puzzle;
        $this->candidates = array_fill(0, 81, 0);
        $this->steps = [];
        $this->tCOL = [];
        $this->tROW = [];
        $this->tBOX = [];
        for ($i = 0; $i < 81; $i++) {
            $this->tCOL[$i] = $i % 9;
            $this->tROW[$i] = intval($i / 9);
            $this->tBOX[$i] = 3 * intval($this->tROW[$i] / 3) + intval($this->tCOL[$i] / 3);
        }
        $this->initCandidates();
    }
    private function parsePuzzle($text) {
        $cleaned = preg_replace('/[^0-9.]/', '', $text);
        $values = array_fill(0, 81, 0);
        for ($i = 0; $i < 81 && $i < strlen($cleaned); $i++) {
            $ch = $cleaned[$i];
            $values[$i] = ($ch === '.' || $ch === '0') ? 0 : intval($ch);
        }
        return $values;
    }
    private function COL($i) { return $this->tCOL[$i]; }
    private function ROW($i) { return $this->tROW[$i]; }
    private function BOX($i) { return $this->tBOX[$i]; }
    private function getCoordStr($i) {
        return "(" . ($this->COL($i) + 1) . "," . ($this->ROW($i) + 1) . ")";
    }
    private function n2b($n) { return 1 << ($n - 1); }
    private function bc($n) {
        $c = 0;
        for ($i = 0; $i < 9; $i++) {
            if ($n & (1 << $i)) $c++;
        }
        return $c;
    }
    private function b2n($mask) {
        for ($i = 0; $i < 9; $i++) {
            if ($mask & (1 << $i)) return $i + 1;
        }
        return 0;
    }
    private function getIdxFromColRow($col, $row) {
        return 9 * $row + $col;
    }
    private function getIdxFromBoxIdx($box, $i) {
        $bcol = $box % 3;
        $brow = intval($box / 3);
        $ccol = $i % 3;
        $crow = intval($i / 3);
        return 9 * (3 * $brow + $crow) + 3 * $bcol + $ccol;
    }
    private function isSolved($i) {
        return $this->puzzle[$i] !== 0;
    }
    private function isSingle($candidateMask) {
        $c = 0;
        $n = 0;
        for ($i = 0; $i < 9; $i++) {
            if ($candidateMask & (1 << $i)) {
                $c++;
                $n = $i + 1;
            }
        }
        return $c === 1 ? $n : 0;
    }
    private function getCandidateListOfBox($box, &$list, &$idx) {
        for ($cell = 0; $cell < 9; $cell++) {
            $i = $this->getIdxFromBoxIdx($box, $cell);
            $list[$cell] = $this->isSolved($i) ? 0 : $this->candidates[$i];
            $idx[$cell] = $i;
        }
    }
    private function getCandidateListOfCol($col, &$list, &$idx) {
        for ($row = 0; $row < 9; $row++) {
            $i = $this->getIdxFromColRow($col, $row);
            $list[$row] = $this->isSolved($i) ? 0 : $this->candidates[$i];
            $idx[$row] = $i;
        }
    }
    private function getCandidateListOfRow($row, &$list, &$idx) {
        for ($col = 0; $col < 9; $col++) {
            $i = $this->getIdxFromColRow($col, $row);
            $list[$col] = $this->isSolved($i) ? 0 : $this->candidates[$i];
            $idx[$col] = $i;
        }
    }
    private function getCandidateCountOfList($candidateList, $n, &$cells) {
        $count = 0;
        $mask = $this->n2b($n);
        $cells = [];
        for ($i = 0; $i < 9; $i++) {
            if ($candidateList[$i] & $mask) {
                $cells[$count] = $i;
                $count++;
            }
        }
        return $count;
    }
    private function initCandidates() {
        for ($row = 0; $row < 9; $row++) {
            for ($col = 0; $col < 9; $col++) {
                $i = $this->getIdxFromColRow($col, $row);
                if (!$this->isSolved($i)) {
                    $this->candidates[$i] = 0x1ff;
                } else {
                    $this->candidates[$i] = $this->n2b($this->puzzle[$i]);
                }
            }
        }
        $this->updateCandidates();
    }
    private function updateCandidates() {
        for ($box = 0; $box < 9; $box++) {
            for ($cell = 0; $cell < 9; $cell++) {
                $index = $this->getIdxFromBoxIdx($box, $cell);
                if (!$this->isSolved($index)) continue;
                $n = $this->puzzle[$index];
                for ($i = 0; $i < 9; $i++) {
                    if ($i !== $cell) {
                        $this->candidates[$this->getIdxFromBoxIdx($box, $i)] &= ~$this->n2b($n);
                    }
                }
            }
        }
        for ($col = 0; $col < 9; $col++) {
            for ($row = 0; $row < 9; $row++) {
                $index = $this->getIdxFromColRow($col, $row);
                if (!$this->isSolved($index)) continue;
                $n = $this->puzzle[$index];
                for ($i = 0; $i < 9; $i++) {
                    if ($i !== $row) {
                        $this->candidates[$this->getIdxFromColRow($col, $i)] &= ~$this->n2b($n);
                    }
                }
            }
        }
        for ($row = 0; $row < 9; $row++) {
            for ($col = 0; $col < 9; $col++) {
                $index = $this->getIdxFromColRow($col, $row);
                if (!$this->isSolved($index)) continue;
                $n = $this->puzzle[$index];
                for ($i = 0; $i < 9; $i++) {
                    if ($i !== $col) {
                        $this->candidates[$this->getIdxFromColRow($i, $row)] &= ~$this->n2b($n);
                    }
                }
            }
        }
    }
    private function findSingle(&$c, &$idx, $unitType, $unitIndex) {
        for ($n = 1; $n <= 9; $n++) {
            $cell = [];
            if ($this->getCandidateCountOfList($c, $n, $cell) !== 1) continue;
            $i = $idx[$cell[0]];
            $this->puzzle[$i] = $n;
            $this->candidates[$i] = $this->n2b($n);
            $unitName = $unitType === "box" ? "第" . ($unitIndex + 1) . "宫" : 
                       ($unitType === "row" ? "第" . ($unitIndex + 1) . "行" : "第" . ($unitIndex + 1) . "列");
            return [
                'success' => true,
                'cell' => $i,
                'value' => $n,
                'description' => $unitName . "中，数字" . $n . "只能出现在" . $this->getCoordStr($i) . "，因此" . $this->getCoordStr($i) . "=" . $n . "。"
            ];
        }
        return ['success' => false];
    }
    private function p_findSingle() {
        if ($this->hs_ns === "2") {
            for ($i = 0; $i < 81; $i++) {
                if ($this->isSolved($i)) continue;
                $n = $this->isSingle($this->candidates[$i]);
                if ($n === 0) continue;
                $this->puzzle[$i] = $n;
                $this->candidates[$i] = $this->n2b($n);
                return [
                    'technique' => 'NakedSingle',
                    'cell' => $i,
                    'value' => $n,
                    'description' => "坐标" . $this->getCoordStr($i) . "的候选数只有" . $n . "，因此" . $this->getCoordStr($i) . "=" . $n . "。"
                ];
            }
        }
        $c = [];
        $idx = [];
        for ($i = 0; $i < 9; $i++) {
            $this->getCandidateListOfBox($i, $c, $idx);
            $result = $this->findSingle($c, $idx, "box", $i);
            if ($result['success']) {
                $this->updateCandidates();
                return [
                    'technique' => 'HiddenSingle',
                    'cell' => $result['cell'],
                    'value' => $result['value'],
                    'description' => $result['description']
                ];
            }
            $this->getCandidateListOfCol($i, $c, $idx);
            $result = $this->findSingle($c, $idx, "col", $i);
            if ($result['success']) {
                $this->updateCandidates();
                return [
                    'technique' => 'HiddenSingle',
                    'cell' => $result['cell'],
                    'value' => $result['value'],
                    'description' => $result['description']
                ];
            }
            $this->getCandidateListOfRow($i, $c, $idx);
            $result = $this->findSingle($c, $idx, "row", $i);
            if ($result['success']) {
                $this->updateCandidates();
                return [
                    'technique' => 'HiddenSingle',
                    'cell' => $result['cell'],
                    'value' => $result['value'],
                    'description' => $result['description']
                ];
            }
        }
        if ($this->hs_ns === "1") {
            for ($i = 0; $i < 81; $i++) {
                if ($this->isSolved($i)) continue;
                $n = $this->isSingle($this->candidates[$i]);
                if ($n === 0) continue;
                $this->puzzle[$i] = $n;
                $this->candidates[$i] = $this->n2b($n);
                return [
                    'technique' => 'NakedSingle',
                    'cell' => $i,
                    'value' => $n,
                    'description' => "坐标" . $this->getCoordStr($i) . "的候选数只有" . $n . "，因此" . $this->getCoordStr($i) . "=" . $n . "。"
                ];
            }
        }
        return null;
    }
    private function findClaiming(&$c, &$idx, $unitType, $unitIndex) {
        for ($n = 1; $n <= 9; $n++) {
            $cell = [];
            $count = $this->getCandidateCountOfList($c, $n, $cell);
            if ($count !== 2 && $count !== 3) continue;
            if ($count === 2) $cell[2] = $cell[0];
            if ($this->BOX($idx[$cell[0]]) !== $this->BOX($idx[$cell[1]]) || 
                $this->BOX($idx[$cell[0]]) !== $this->BOX($idx[$cell[2]])) continue;
            $c2 = [];
            $idx2 = [];
            $box = $this->BOX($idx[$cell[0]]);
            $this->getCandidateListOfBox($box, $c2, $idx2);
            $mask = $this->n2b($n);
            $excludedCells = [];
            for ($i = 0; $i < 9; $i++) {
                $index = $idx2[$i];
                if ($this->isSolved($index)) continue;
                $again = false;
                for ($j = 0; $j < $count; $j++) {
                    if ($index === $idx[$cell[$j]]) {
                        $again = true;
                        break;
                    }
                }
                if ($again) continue;
                if (($this->candidates[$index] & $mask) === 0) continue;
                $this->candidates[$index] &= ~$mask;
                $excludedCells[] = $index;
            }
            if (empty($excludedCells)) continue;
            $unitName = $unitType === "row" ? "第" . ($unitIndex + 1) . "行" : "第" . ($unitIndex + 1) . "列";
            $boxName = "第" . ($box + 1) . "宫";
            $cellsStr = implode("和", array_map(function($j) use ($idx, $cell, $this) {
                return $this->getCoordStr($idx[$cell[$j]]);
            }, range(0, $count - 1)));
            $excludedStr = implode("、", array_map([$this, 'getCoordStr'], $excludedCells));
            return [
                'success' => true,
                'cells' => array_map(function($j) use ($idx, $cell) { return $idx[$cell[$j]]; }, range(0, $count - 1)),
                'excluded' => $excludedCells,
                'value' => $n,
                'description' => $unitName . "中，数字" . $n . "的候选位置" . $cellsStr . "都在" . $boxName . "内，因此" . $boxName . "其他位置" . $excludedStr . "排除候选值" . $n . "。"
            ];
        }
        return ['success' => false];
    }
    private function p_findClaiming() {
        $c = [];
        $idx = [];
        for ($i = 0; $i < 9; $i++) {
            $this->getCandidateListOfCol($i, $c, $idx);
            $result = $this->findClaiming($c, $idx, "col", $i);
            if ($result['success']) {
                $this->updateCandidates();
                return [
                    'technique' => 'Claiming',
                    'cells' => $result['cells'],
                    'excluded' => $result['excluded'],
                    'value' => $result['value'],
                    'description' => $result['description']
                ];
            }
            $this->getCandidateListOfRow($i, $c, $idx);
            $result = $this->findClaiming($c, $idx, "row", $i);
            if ($result['success']) {
                $this->updateCandidates();
                return [
                    'technique' => 'Claiming',
                    'cells' => $result['cells'],
                    'excluded' => $result['excluded'],
                    'value' => $result['value'],
                    'description' => $result['description']
                ];
            }
        }
        return null;
    }
    private function p_findPointing() {
        $c = [];
        $idx = [];
        $cell = [];
        for ($box = 0; $box < 9; $box++) {
            $this->getCandidateListOfBox($box, $c, $idx);
            for ($n = 1; $n <= 9; $n++) {
                $count = $this->getCandidateCountOfList($c, $n, $cell);
                if ($count !== 2 && $count !== 3) continue;
                $col = [];
                $row = [];
                for ($i = 0; $i < $count; $i++) {
                    $index = $idx[$cell[$i]];
                    $col[$i] = $this->COL($index) % 3;
                    $row[$i] = $this->ROW($index) % 3;
                }
                if ($count === 2) {
                    $col[2] = $col[0];
                    $row[2] = $row[0];
                }
                $c2 = [];
                $idx2 = [];
                $unitType = "";
                $unitIndex = -1;
                if ($col[0] === $col[1] && $col[0] === $col[2]) {
                    $unitIndex = $this->COL($idx[$cell[0]]);
                    $this->getCandidateListOfCol($unitIndex, $c2, $idx2);
                    $unitType = "col";
                } else if ($row[0] === $row[1] && $row[0] === $row[2]) {
                    $unitIndex = $this->ROW($idx[$cell[0]]);
                    $this->getCandidateListOfRow($unitIndex, $c2, $idx2);
                    $unitType = "row";
                } else {
                    continue;
                }
                $mask = $this->n2b($n);
                $excludedCells = [];
                for ($i = 0; $i < 9; $i++) {
                    $index = $idx2[$i];
                    if ($this->isSolved($index)) continue;
                    $again = false;
                    for ($j = 0; $j < $count; $j++) {
                        if ($index === $idx[$cell[$j]]) {
                            $again = true;
                            break;
                        }
                    }
                    if ($again) continue;
                    if (($this->candidates[$index] & $mask) === 0) continue;
                    $this->candidates[$index] &= ~$mask;
                    $excludedCells[] = $index;
                }
                if (empty($excludedCells)) continue;
                $boxName = "第" . ($box + 1) . "宫";
                $unitName = $unitType === "row" ? "第" . ($unitIndex + 1) . "行" : "第" . ($unitIndex + 1) . "列";
                $cellsStr = implode("和", array_map(function($j) use ($idx, $cell, $this) {
                    return $this->getCoordStr($idx[$cell[$j]]);
                }, range(0, $count - 1)));
                $excludedStr = implode("、", array_map([$this, 'getCoordStr'], $excludedCells));
                $this->updateCandidates();
                return [
                    'technique' => 'Pointing',
                    'cells' => array_map(function($j) use ($idx, $cell) { return $idx[$cell[$j]]; }, range(0, $count - 1)),
                    'excluded' => $excludedCells,
                    'value' => $n,
                    'description' => $boxName . "中，数字" . $n . "的候选位置" . $cellsStr . "都在" . $unitName . "上，因此" . $unitName . "其他位置" . $excludedStr . "排除候选值" . $n . "。"
                ];
            }
        }
        return null;
    }
    private function findNakedSet(&$c, &$idx, $n, $unitType, $unitIndex) {
        for ($mask = 0; $mask < 0x1ff; $mask++) {
            if ($this->bc($mask) !== $n) continue;
            $pos = [];
            $i2 = 0;
            for ($j = 0; $j < 9; $j++) {
                if ($c[$j] && ($c[$j] & ~$mask) === 0) {
                    $pos[$i2] = $idx[$j];
                    $i2++;
                }
            }
            if ($i2 !== $n) continue;
            $excludedCells = [];
            $excludedValues = [];
            for ($j = 0; $j < 9; $j++) {
                $index = $idx[$j];
                if ($this->isSolved($index)) continue;
                $again = false;
                for ($k = 0; $k < $n; $k++) {
                    if ($index === $pos[$k]) {
                        $again = true;
                        break;
                    }
                }
                if ($again) continue;
                $removed = $this->candidates[$idx[$j]] & $mask;
                if ($removed === 0) continue;
                $this->candidates[$idx[$j]] &= ~$mask;
                $excludedCells[] = $index;
                for ($bit = 0; $bit < 9; $bit++) {
                    if ($removed & (1 << $bit)) {
                        $excludedValues[] = $bit + 1;
                    }
                }
            }
            if (empty($excludedCells)) continue;
            $unitName = $unitType === "box" ? "第" . ($unitIndex + 1) . "宫" : 
                       ($unitType === "row" ? "第" . ($unitIndex + 1) . "行" : "第" . ($unitIndex + 1) . "列");
            $cellsStr = implode("和", array_map([$this, 'getCoordStr'], $pos));
            $valuesStr = implode(",", array_filter(range(1, 9), function($v) use ($mask) {
                return $mask & (1 << ($v - 1));
            }));
            $excludedStr = implode("、", array_map([$this, 'getCoordStr'], $excludedCells));
            $excludedValuesStr = implode("、", array_unique($excludedValues));
            $subsetName = $n === 2 ? "数对" : ($n === 3 ? "数组" : "数组");
            $this->updateCandidates();
            return [
                'technique' => 'NakedSubset',
                'cells' => $pos,
                'excluded' => $excludedCells,
                'values' => array_filter(range(1, 9), function($v) use ($mask) {
                    return $mask & (1 << ($v - 1));
                }),
                'excludedValues' => array_unique($excludedValues),
                'description' => $unitName . "中，坐标" . $cellsStr . "形成裸" . $subsetName . "[" . $valuesStr . "]，删除" . $excludedStr . "的相同候选值" . $excludedValuesStr . "。"
            ];
        }
        return null;
    }
    private function p_findSubset() {
        $c = [];
        $idx = [];
        for ($n = 2; $n <= 4; $n++) {
            for ($i = 0; $i < 9; $i++) {
                $this->getCandidateListOfBox($i, $c, $idx);
                $result = $this->findNakedSet($c, $idx, $n, "box", $i);
                if ($result) return $result;
                $this->getCandidateListOfCol($i, $c, $idx);
                $result = $this->findNakedSet($c, $idx, $n, "col", $i);
                if ($result) return $result;
                $this->getCandidateListOfRow($i, $c, $idx);
                $result = $this->findNakedSet($c, $idx, $n, "row", $i);
                if ($result) return $result;
            }
        }
        return null;
    }
    private function p_findXWings() {
        $c = []; $idx = []; $cell = []; $c2 = []; $idx2 = []; $cell2 = [];
        $c34 = [[], []]; $idx34 = [[], []];
        for ($n = 1; $n <= 9; $n++) {
            for ($i = 0; $i < 9; $i++) {
                $this->getCandidateListOfRow($i, $c, $idx);
                if ($this->getCandidateCountOfList($c, $n, $cell) !== 2) continue;
                for ($j = $i + 1; $j < 9; $j++) {
                    $this->getCandidateListOfRow($j, $c2, $idx2);
                    if ($this->getCandidateCountOfList($c2, $n, $cell2) !== 2) continue;
                    if ($cell[0] !== $cell2[0] || $cell[1] !== $cell2[1]) continue;
                    $this->getCandidateListOfCol($cell[0], $c34[0], $idx34[0]);
                    $this->getCandidateListOfCol($cell[1], $c34[1], $idx34[1]);
                    $excludedCells = [];
                    for ($l = 0; $l < 2; $l++) {
                        for ($k = 0; $k < 9; $k++) {
                            if ($i === $k || $j === $k || $this->isSolved($idx34[$l][$k])) continue;
                            if ($c34[$l][$k] & $this->n2b($n)) {
                                $this->candidates[$idx34[$l][$k]] &= ~$this->n2b($n);
                                $excludedCells[] = $idx34[$l][$k];
                            }
                        }
                    }
                    if (empty($excludedCells)) continue;
                    $excludedStr = implode("、", array_map([$this, 'getCoordStr'], $excludedCells));
                    $this->updateCandidates();
                    return [
                        'technique' => 'X_Wing',
                        'cells' => [$idx[$cell[0]], $idx[$cell[1]], $idx2[$cell[0]], $idx2[$cell[1]]],
                        'excluded' => $excludedCells,
                        'value' => $n,
                        'description' => "第" . ($i + 1) . "行和第" . ($j + 1) . "行中，数字" . $n . "的候选位置形成X翼，第" . ($cell[0] + 1) . "列和第" . ($cell[1] + 1) . "列其他位置" . $excludedStr . "排除候选值" . $n . "。"
                    ];
                }
            }
            for ($i = 0; $i < 9; $i++) {
                $this->getCandidateListOfCol($i, $c, $idx);
                if ($this->getCandidateCountOfList($c, $n, $cell) !== 2) continue;
                for ($j = $i + 1; $j < 9; $j++) {
                    $this->getCandidateListOfCol($j, $c2, $idx2);
                    if ($this->getCandidateCountOfList($c2, $n, $cell2) !== 2) continue;
                    if ($cell[0] !== $cell2[0] || $cell[1] !== $cell2[1]) continue;
                    $this->getCandidateListOfRow($cell[0], $c34[0], $idx34[0]);
                    $this->getCandidateListOfRow($cell[1], $c34[1], $idx34[1]);
                    $excludedCells = [];
                    for ($l = 0; $l < 2; $l++) {
                        for ($k = 0; $k < 9; $k++) {
                            if ($i === $k || $j === $k || $this->isSolved($idx34[$l][$k])) continue;
                            if ($c34[$l][$k] & $this->n2b($n)) {
                                $this->candidates[$idx34[$l][$k]] &= ~$this->n2b($n);
                                $excludedCells[] = $idx34[$l][$k];
                            }
                        }
                    }
                    if (empty($excludedCells)) continue;
                    $excludedStr = implode("、", array_map([$this, 'getCoordStr'], $excludedCells));
                    $this->updateCandidates();
                    return [
                        'technique' => 'X_Wing',
                        'cells' => [$idx[$cell[0]], $idx[$cell[1]], $idx2[$cell[0]], $idx2[$cell[1]]],
                        'excluded' => $excludedCells,
                        'value' => $n,
                        'description' => "第" . ($i + 1) . "列和第" . ($j + 1) . "列中，数字" . $n . "的候选位置形成X翼，第" . ($cell[0] + 1) . "行和第" . ($cell[1] + 1) . "行其他位置" . $excludedStr . "排除候选值" . $n . "。"
                    ];
                }
            }
        }
        return null;
    }
    private function p_findSwordfish() {
        return null;
    }
    private function p_findXyWings() {
        return null;
    }
    private function p_findWWings() {
        return null;
    }
    private function p_findXyzWings() {
        return null;
    }
    private function p_findXChains() {
        return null;
    }
    private function p_findXyChains() {
        return null;
    }
    private function p_findSkyscraper() {
        return null;
    }
    private function p_findUniqueRectangle() {
        return null;
    }
    public function solve($auto = true) {
        $round = 0;
        $this->steps[] = [
            'step' => $round,
            'technique' => 'Initial',
            'description' => '初始题目状态',
            'puzzle' => $this->puzzle,
            'candidates' => $this->candidates
        ];
        $round++;
        $pattern = [
            [$this, 'p_findSingle'],
            [$this, 'p_findClaiming'],
            [$this, 'p_findPointing'],
            [$this, 'p_findSubset'],
            [$this, 'p_findXWings'],
            [$this, 'p_findSwordfish'],
            [$this, 'p_findXyWings'],
            [$this, 'p_findWWings'],
            [$this, 'p_findXyzWings'],
            [$this, 'p_findXChains'],
            [$this, 'p_findXyChains'],
            [$this, 'p_findSkyscraper'],
            [$this, 'p_findUniqueRectangle'],
        ];
        while (true) {
            $found = false;
            foreach ($pattern as $technique) {
                $result = call_user_func($technique);
                if ($result) {
                    $this->steps[] = array_merge([
                        'step' => $round,
                        'puzzle' => $this->puzzle,
                        'candidates' => $this->candidates
                    ], $result);
                    $round++;
                    $found = true;
                    $this->updateCandidates();
                    break;
                }
            }
            if (!$found || !$auto) break;
        }
        return $this->steps;
    }
    public function getSteps() {
        return $this->steps;
    }
    public function getPuzzle() {
        return $this->puzzle;
    }
    public function getOriginalPuzzle() {
        return $this->originalPuzzle;
    }
    public function getCandidates() {
        return $this->candidates;
    }
}
$solver = new SudokuSolver($puzzleInput);
$steps = $solver->solve(true);
$originalPuzzle = $solver->getOriginalPuzzle();
$stepsJson = json_encode($steps, JSON_UNESCAPED_UNICODE);
$originalPuzzleJson = json_encode($originalPuzzle);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>数独分步解独工具</title>
<style>
:root{--original-digit:#0066cc;--solved-digit:#cc6600;--candidate-gray:#999999;}
*{box-sizing:border-box;}
body{margin:0;padding:24px;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;background:#f5f5f5;color:#222;line-height:1.6;}
h1{margin:0 0 16px;font-size:28px;}
p{margin:0 0 12px;}
.layout{display:grid;grid-template-columns:minmax(320px,auto) minmax(240px,1fr);gap:24px;}
.toolbar{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;}
button{border:1px solid rgba(0,0,0,0.15);border-radius:6px;padding:6px 14px;font-size:14px;cursor:pointer;background:white;color:inherit;transition:background 0.2s ease,color 0.2s ease,border 0.2s ease;}
button:hover,button:focus-visible{background:#0d6efd;border-color:#0d6efd;color:white;}
button.danger:hover,button.danger:focus-visible{background:#d6336c;border-color:#d6336c;}
textarea{width:100%;min-height:120px;padding:12px;border:1px solid rgba(0,0,0,0.15);border-radius:8px;font-family:"Iosevka","Fira Code",monospace;resize:vertical;background:white;color:inherit;}
label{font-weight:600;display:block;margin-bottom:6px;}
.board-wrapper{padding:12px;border-radius:12px;background:white;border:3px solid #000000;position:relative;display:inline-block;}
canvas{display:block;max-width:100%;height:auto;border:2px solid #333333;}
.share-row{display:flex;gap:12px;margin-top:8px;align-items:center;flex-wrap:wrap;}
.share-row input{flex:1 1 220px;padding:6px 10px;border:1px solid rgba(0,0,0,0.15);border-radius:6px;font-size:14px;background:white;color:inherit;}
.digit-panel{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;}
.digit-panel button{width:40px;text-align:center;}
.digit-panel button[data-digit="0"]{flex:1 0 80px;}
.steps-container{margin-top:24px;display:flex;flex-direction:column;gap:18px;}
.step-card{background:white;border:3px solid #000000;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.1);}
.step-card h2{margin:0 0 12px;font-size:20px;color:#333;}
.step-description{margin:12px 0;padding:12px;background:#f8f9fa;border-radius:6px;border-left:4px solid #0d6efd;font-size:14px;line-height:1.6;color:#495057;}
.step-canvas-wrapper{margin:16px 0;display:inline-block;border:3px solid #000000;border-radius:8px;padding:10px;background:white;}
.step-canvas-wrapper canvas{border:2px solid #333333;}
.status{margin-top:12px;padding:8px 12px;background:#e7f3ff;border-radius:6px;font-size:14px;color:#004085;}
footer{margin-top:32px;padding:16px;background:#f8f9fa;border-radius:8px;font-size:14px;color:#6c757d;}
@media print{
*{background:white!important;color:black!important;}
.toolbar,.share-row,textarea,label,footer,.status{display:none!important;}
.layout{grid-template-columns:1fr!important;gap:12px!important;}
.board-wrapper,.step-card,.step-canvas-wrapper{border:3px solid #000!important;page-break-inside:avoid!important;}
.step-card{page-break-after:always!important;page-break-before:auto!important;margin-bottom:0!important;padding-bottom:20px!important;}
.step-card:last-child{page-break-after:auto!important;}
h1{page-break-after:avoid!important;}
}
@media (max-width:980px){body{padding:16px;}.layout{grid-template-columns:1fr;}}
</style>
</head>
<body>
<h1>数独分步解独工具</h1>
<p>该工具基于候选数筛选和高级链式技巧，能够逐步演示数独的解题过程。</p>
<div class="layout">
<section>
<div class="toolbar">
<button id="btn-reset" class="danger">清空棋盘</button>
<button id="btn-example">导入示例</button>
<button id="btn-print">打印</button>
</div>
<div class="board-wrapper">
<canvas id="board" width="666" height="666">Canvas not supported.</canvas>
</div>
<div class="share-row">
<label for="puzzle-input">批量导入（支持 0/ . 表示空）：</label>
<textarea id="puzzle-input" placeholder="例如：530070000600195000098000060..."><?php echo htmlspecialchars($puzzleInput); ?></textarea>
<button id="btn-apply">应用到棋盘</button>
</div>
<p class="status" id="status-text">已自动求解，查看下方步骤。</p>
</section>
<section>
<div class="steps-container" id="steps"></div>
</section>
</div>
<footer>提示：所有解题步骤已由PHP在服务器端计算完成。</footer>
<script>
(function(){
const CHAR_W=14,CHAR_H=14,HALF_CHAR_W=Math.floor(CHAR_W/2),HALF_CHAR_H=Math.floor(CHAR_H/2),CELL_W=3*CHAR_W,CELL_H=3*CHAR_H,PUZZLE_W=9*(1+CELL_W),PUZZLE_H=9*(1+CELL_H);
const tech=<?php echo json_encode($tech, JSON_UNESCAPED_UNICODE); ?>;
const steps=<?php echo $stepsJson; ?>;
const originalPuzzle=<?php echo $originalPuzzleJson; ?>;
function COL(i){return i%9;}
function ROW(i){return Math.floor(i/9);}
function BOX(i){return 3*Math.floor(ROW(i)/3)+Math.floor(COL(i)/3);}
function n2b(n){return 1<<(n-1);}
function isOriginal(i){return originalPuzzle[i]!==0;}
function renderPuzzle(name,puzzle,candidates,highlightCells,excludeCells){
const c=document.getElementById(name);
if(!c)return;
const ctx=c.getContext('2d');
ctx.lineWidth=1;
ctx.textAlign="center";
ctx.textBaseline="middle";
ctx.fillStyle='#FFFFFF';
ctx.fillRect(0,0,PUZZLE_W,PUZZLE_H);
ctx.fillStyle='Black';
for(let i=0;i<81;i++){
const x=COL(i)*(1+CELL_W),y=ROW(i)*(1+CELL_H);
if(highlightCells&&highlightCells.indexOf(i)!==-1){
ctx.fillStyle='#B6FF00';
ctx.fillRect(x,y,CELL_W+1,CELL_H+1);
ctx.fillStyle='Black';
}else if(excludeCells&&excludeCells.indexOf(i)!==-1){
ctx.fillStyle='#ffebee';
ctx.fillRect(x,y,CELL_W+1,CELL_H+1);
ctx.fillStyle='Black';
}
const cx=x+Math.floor(CHAR_W/2),cy=y+Math.floor(CHAR_H/2)+1;
if(puzzle[i]!==0){
ctx.font='30px Arial';
if(isOriginal(i)){
ctx.fillStyle='#0066cc';
}else{
ctx.fillStyle='#cc6600';
}
ctx.fillText(puzzle[i],cx+CHAR_W,cy+CHAR_H);
ctx.fillStyle='Black';
}else{
ctx.font=CHAR_H+'px sans-serif';
ctx.fillStyle='#999999';
for(let j=0;j<9;j++){
if(candidates[i]&n2b(1+j)){
ctx.fillText(1+j,cx+(j%3)*CHAR_W,cy+Math.floor(j/3)*CHAR_H);
}
}
}
}
for(let i=1;i<9;i++){
if(i%3===0){
ctx.strokeStyle='Black';
}else{
ctx.strokeStyle='LightGray';
}
const x=i*(1+CELL_W),y=i*(1+CELL_H);
ctx.beginPath();
ctx.moveTo(x,0);
ctx.lineTo(x,PUZZLE_H);
ctx.moveTo(0,y);
ctx.lineTo(PUZZLE_W,y);
ctx.stroke();
}
}
function init(){
const board=document.getElementById('board');
board.setAttribute('width',PUZZLE_W);
board.setAttribute('height',PUZZLE_H);
if(steps.length>0){
renderPuzzle('board',steps[0].puzzle,steps[0].candidates);
}
const stepsContainer=document.getElementById('steps');
steps.forEach(function(step){
const card=document.createElement('div');
card.className='step-card';
const h2=document.createElement('h2');
h2.textContent='步骤 '+step.step+(step.technique&&step.technique!=='Initial'?': '+tech[step.technique]:'');
card.appendChild(h2);
if(step.description){
const descDiv=document.createElement('div');
descDiv.className='step-description';
descDiv.textContent=step.description;
card.appendChild(descDiv);
}
const canvasWrapper=document.createElement('div');
canvasWrapper.className='step-canvas-wrapper';
const c=document.createElement('canvas');
c.setAttribute('id','c'+step.step);
c.setAttribute('width',PUZZLE_W);
c.setAttribute('height',PUZZLE_H);
canvasWrapper.appendChild(c);
card.appendChild(canvasWrapper);
stepsContainer.appendChild(card);
const highlightCells=[];
const excludeCells=[];
if(step.cell!==undefined)highlightCells.push(step.cell);
if(step.cells)highlightCells.push.apply(highlightCells,step.cells);
if(step.excluded)excludeCells.push.apply(excludeCells,step.excluded);
renderPuzzle('c'+step.step,step.puzzle,step.candidates,highlightCells,excludeCells);
});
document.getElementById('btn-print').onclick=function(){window.print();};
document.getElementById('btn-example').onclick=function(){
const example="530070000600195000098000060800060003400803001700020006060000280000419005000080079";
document.getElementById('puzzle-input').value=example;
window.location.href='?p='+example;
};
document.getElementById('btn-apply').onclick=function(){
const text=document.getElementById('puzzle-input').value;
window.location.href='?p='+encodeURIComponent(text);
};
}
if(document.readyState==='loading'){
document.addEventListener('DOMContentLoaded',init);
}else{
init();
}
})();
</script>
</body>
</html>
