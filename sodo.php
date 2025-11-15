<?php
/**
 * 数独分步解独工具 - PHP版本
 * 参考 sodo.html 的解独方法实现
 */

header('Content-Type: text/html; charset=utf-8');

// 技术名称映射
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
        
        // 预计算行列宫索引
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
            $values[$i] = ($ch === '.') ? 0 : intval($ch);
        }
        return $values;
    }
    
    private function COL($i) { return $this->tCOL[$i]; }
    private function ROW($i) { return $this->tROW[$i]; }
    private function BOX($i) { return $this->tBOX[$i]; }
    
    private function getCoordStr($i) {
        return "(" . ($this->COL($i) + 1) . "," . ($this->ROW($i) + 1) . ")";
    }
    
    private function n2b($n) {
        return 1 << ($n - 1);
    }
    
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
        $count = 0;
        $n = 0;
        for ($i = 0; $i < 9; $i++) {
            if ($candidateMask & (1 << $i)) {
                $count++;
                $n = $i + 1;
            }
        }
        return $count === 1 ? $n : 0;
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
                $this->candidates[$i] = $this->isSolved($i) ? $this->n2b($this->puzzle[$i]) : 0x1ff;
            }
        }
        $this->updateCandidates();
    }
    
    private function updateCandidates() {
        // 按宫更新
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
        
        // 按列更新
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
        
        // 按行更新
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
                    'description' => "坐标" . $this->getCoordStr($i) . "的候选数只有" . $n . "，因此" . $this->getCoordStr($i) . "=" . $n . "。",
                    'basis' => ['cells' => [$this->getCoordStr($i)]],
                    'conclusion' => ['set' => [$this->getCoordStr($i) => $n]]
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
                    'description' => $result['description'],
                    'basis' => ['unit' => "第" . ($i + 1) . "宫", 'cells' => array_map([$this, 'getCoordStr'], $idx)],
                    'conclusion' => ['set' => [$this->getCoordStr($result['cell']) => $result['value']]]
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
                    'description' => $result['description'],
                    'basis' => ['unit' => "第" . ($i + 1) . "列", 'cells' => array_map([$this, 'getCoordStr'], $idx)],
                    'conclusion' => ['set' => [$this->getCoordStr($result['cell']) => $result['value']]]
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
                    'description' => $result['description'],
                    'basis' => ['unit' => "第" . ($i + 1) . "行", 'cells' => array_map([$this, 'getCoordStr'], $idx)],
                    'conclusion' => ['set' => [$this->getCoordStr($result['cell']) => $result['value']]]
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
                    'description' => "坐标" . $this->getCoordStr($i) . "的候选数只有" . $n . "，因此" . $this->getCoordStr($i) . "=" . $n . "。",
                    'basis' => ['cells' => [$this->getCoordStr($i)]],
                    'conclusion' => ['set' => [$this->getCoordStr($i) => $n]]
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
            $cellsStr = implode("和", array_map(function($j) use ($idx, $cell) {
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
                    'description' => $result['description'],
                    'basis' => ['unit' => "第" . ($i + 1) . "列", 'cells' => array_map([$this, 'getCoordStr'], $result['cells'])],
                    'conclusion' => ['exclude' => array_map(function($i) use ($result) {
                        return $this->getCoordStr($i) . "排除" . $result['value'];
                    }, $result['excluded'])]
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
                    'description' => $result['description'],
                    'basis' => ['unit' => "第" . ($i + 1) . "行", 'cells' => array_map([$this, 'getCoordStr'], $result['cells'])],
                    'conclusion' => ['exclude' => array_map(function($i) use ($result) {
                        return $this->getCoordStr($i) . "排除" . $result['value'];
                    }, $result['excluded'])]
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
                $cellsStr = implode("和", array_map(function($j) use ($idx, $cell) {
                    return $this->getCoordStr($idx[$cell[$j]]);
                }, range(0, $count - 1)));
                $excludedStr = implode("、", array_map([$this, 'getCoordStr'], $excludedCells));
                
                $this->updateCandidates();
                return [
                    'technique' => 'Pointing',
                    'cells' => array_map(function($j) use ($idx, $cell) { return $idx[$cell[$j]]; }, range(0, $count - 1)),
                    'excluded' => $excludedCells,
                    'value' => $n,
                    'description' => $boxName . "中，数字" . $n . "的候选位置" . $cellsStr . "都在" . $unitName . "上，因此" . $unitName . "其他位置" . $excludedStr . "排除候选值" . $n . "。",
                    'basis' => ['unit' => $boxName, 'cells' => array_map(function($j) use ($idx, $cell) {
                        return $this->getCoordStr($idx[$cell[$j]]);
                    }, range(0, $count - 1))],
                    'conclusion' => ['exclude' => array_map(function($i) use ($n) {
                        return $this->getCoordStr($i) . "排除" . $n;
                    }, $excludedCells)]
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
                'description' => $unitName . "中，坐标" . $cellsStr . "形成裸" . $subsetName . "[" . $valuesStr . "]，删除" . $excludedStr . "的相同候选值" . $excludedValuesStr . "。",
                'basis' => ['unit' => $unitName, 'cells' => array_map([$this, 'getCoordStr'], $pos), 'values' => array_filter(range(1, 9), function($v) use ($mask) {
                    return $mask & (1 << ($v - 1));
                })],
                'conclusion' => ['exclude' => array_map(function($i) use ($excludedValues) {
                    return $this->getCoordStr($i) . "排除" . implode("、", $excludedValues);
                }, $excludedCells)]
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
    
    public function solve($auto = true) {
        $round = 0;
        $this->steps[] = [
            'step' => $round,
            'technique' => 'Initial',
            'description' => '初始题目状态',
            'basis' => [],
            'conclusion' => []
        ];
        $round++;
        
        $pattern = [
            [$this, 'p_findSingle'],
            [$this, 'p_findClaiming'],
            [$this, 'p_findPointing'],
            [$this, 'p_findSubset'],
        ];
        
        while (true) {
            $found = false;
            foreach ($pattern as $technique) {
                $result = call_user_func($technique);
                if ($result) {
                    $this->steps[] = array_merge(['step' => $round], $result);
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
}

// 处理请求
$puzzleInput = isset($_GET['p']) ? $_GET['p'] : (isset($_POST['p']) ? $_POST['p'] : '');
$format = isset($_GET['format']) ? $_GET['format'] : 'html';

if (empty($puzzleInput)) {
    $puzzleInput = "530070000600195000098000060800060003400803001700020006060000280000419005000080079";
}

$solver = new SudokuSolver($puzzleInput);
$steps = $solver->solve(true);

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'puzzle' => $solver->getPuzzle(),
        'steps' => $steps
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>数独分步解独工具 - PHP版</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Microsoft YaHei", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .input-section {
            padding: 30px;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        
        .input-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 16px;
            font-family: monospace;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .steps-section {
            padding: 30px;
        }
        
        .step-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .step-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-color: #667eea;
        }
        
        .step-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .step-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            margin-right: 15px;
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }
        
        .step-title {
            flex: 1;
        }
        
        .step-title h2 {
            font-size: 22px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .step-title .technique {
            font-size: 14px;
            color: #667eea;
            font-weight: 600;
        }
        
        .step-content {
            margin-top: 15px;
        }
        
        .step-description {
            font-size: 16px;
            line-height: 1.8;
            color: #495057;
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .step-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .detail-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
        
        .detail-box h3 {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .detail-box .content {
            font-size: 15px;
            color: #333;
            line-height: 1.6;
        }
        
        .detail-box.basis {
            border-left-color: #17a2b8;
        }
        
        .detail-box.conclusion {
            border-left-color: #ffc107;
        }
        
        .detail-box.conclusion.set {
            border-left-color: #28a745;
        }
        
        .detail-box.conclusion.exclude {
            border-left-color: #dc3545;
        }
        
        .json-section {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .json-section h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .json-output {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 8px;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .badge-info {
            background: #17a2b8;
            color: white;
        }
        
        .badge-warning {
            background: #ffc107;
            color: #333;
        }
        
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        
        @media (max-width: 768px) {
            .step-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔢 数独分步解独工具</h1>
            <p>基于候选数筛选和高级链式技巧的智能解独系统</p>
        </div>
        
        <div class="input-section">
            <form method="GET" action="">
                <div class="input-group">
                    <label for="puzzle">数独题目（81个字符，0或.表示空格）：</label>
                    <input type="text" id="puzzle" name="p" value="<?php echo htmlspecialchars($puzzleInput); ?>" 
                           placeholder="例如：530070000600195000098000060800060003400803001700020006060000280000419005000080079" 
                           maxlength="81">
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">求解</button>
                    <a href="?format=json&p=<?php echo urlencode($puzzleInput); ?>" class="btn btn-secondary" target="_blank">查看JSON</a>
                </div>
            </form>
        </div>
        
        <div class="steps-section">
            <?php if (!empty($steps)): ?>
                <?php foreach ($steps as $step): ?>
                    <div class="step-card">
                        <div class="step-header">
                            <div class="step-number"><?php echo $step['step']; ?></div>
                            <div class="step-title">
                                <h2>
                                    <?php 
                                    if ($step['step'] === 0) {
                                        echo "初始状态";
                                    } else {
                                        echo isset($tech[$step['technique']]) ? $tech[$step['technique']] : $step['technique'];
                                    }
                                    ?>
                                </h2>
                                <?php if ($step['step'] > 0): ?>
                                    <span class="technique"><?php echo $step['technique']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="step-content">
                            <?php if (isset($step['description'])): ?>
                                <div class="step-description">
                                    <?php echo htmlspecialchars($step['description']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($step['basis']) || isset($step['conclusion'])): ?>
                                <div class="step-details">
                                    <?php if (isset($step['basis']) && !empty($step['basis'])): ?>
                                        <div class="detail-box basis">
                                            <h3>📐 计算依据</h3>
                                            <div class="content">
                                                <?php 
                                                if (isset($step['basis']['unit'])) {
                                                    echo "<strong>单元：</strong>" . htmlspecialchars($step['basis']['unit']) . "<br>";
                                                }
                                                if (isset($step['basis']['cells'])) {
                                                    echo "<strong>单元格：</strong>" . implode("、", array_map('htmlspecialchars', $step['basis']['cells'])) . "<br>";
                                                }
                                                if (isset($step['basis']['values'])) {
                                                    echo "<strong>数值：</strong>[" . implode(",", $step['basis']['values']) . "]<br>";
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($step['conclusion']) && !empty($step['conclusion'])): ?>
                                        <div class="detail-box conclusion <?php echo isset($step['conclusion']['set']) ? 'set' : 'exclude'; ?>">
                                            <h3>✅ 结论</h3>
                                            <div class="content">
                                                <?php 
                                                if (isset($step['conclusion']['set'])) {
                                                    echo "<span class='badge badge-success'>得到值</span><br>";
                                                    foreach ($step['conclusion']['set'] as $coord => $value) {
                                                        echo htmlspecialchars($coord) . " = <strong>" . $value . "</strong><br>";
                                                    }
                                                }
                                                if (isset($step['conclusion']['exclude'])) {
                                                    echo "<span class='badge badge-danger'>排除值</span><br>";
                                                    foreach ($step['conclusion']['exclude'] as $excl) {
                                                        echo htmlspecialchars($excl) . "<br>";
                                                    }
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <div class="json-section">
                <h3>📄 JSON 数据</h3>
                <div class="json-output">
                    <pre><?php echo json_encode(['puzzle' => $solver->getPuzzle(), 'steps' => $steps], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?></pre>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
