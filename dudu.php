<?php
/**
 * 数独分步解独工具 - PHP7版本
 * 根据 test.js 思路制作，使用 Canvas 渲染
 * 原题数字：蓝色（#0066cc）
 * 解出数字：橙色（#cc6600）
 * 候选数字：浅灰色（#999999）
 */

header('Content-Type: text/html; charset=utf-8');

// 处理输入
$puzzleInput = isset($_GET['p']) ? $_GET['p'] : (isset($_POST['p']) ? $_POST['p'] : '');
if (empty($puzzleInput)) {
    $puzzleInput = "530070000600195000098000060800060003400803001700020006060000280000419005000080079";
}

// 解析数独题目
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

$initialPuzzle = parsePuzzle($puzzleInput);
$puzzleJson = json_encode($initialPuzzle);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>数独分步解独工具</title>
  <style>
    :root {
      --original-digit: #0066cc;
      --solved-digit: #cc6600;
      --candidate-gray: #999999;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 24px;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", "Microsoft YaHei", sans-serif;
      background: #f5f5f5;
      color: #222;
      line-height: 1.6;
    }

    h1 {
      margin: 0 0 16px;
      font-size: 28px;
    }

    p {
      margin: 0 0 12px;
    }

    .layout {
      display: grid;
      grid-template-columns: minmax(320px, auto) minmax(240px, 1fr);
      gap: 24px;
    }

    .toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 16px;
    }

    button {
      border: 1px solid rgba(0, 0, 0, 0.15);
      border-radius: 6px;
      padding: 6px 14px;
      font-size: 14px;
      cursor: pointer;
      background: white;
      color: inherit;
      transition: background 0.2s ease, color 0.2s ease, border 0.2s ease;
    }

    button:hover,
    button:focus-visible {
      background: #0d6efd;
      border-color: #0d6efd;
      color: white;
    }

    button.danger:hover,
    button.danger:focus-visible {
      background: #d6336c;
      border-color: #d6336c;
    }

    textarea {
      width: 100%;
      min-height: 120px;
      padding: 12px;
      border: 1px solid rgba(0, 0, 0, 0.15);
      border-radius: 8px;
      font-family: "Iosevka", "Fira Code", monospace;
      resize: vertical;
      background: white;
      color: inherit;
    }

    label {
      font-weight: 600;
      display: block;
      margin-bottom: 6px;
    }

    .board-wrapper {
      padding: 12px;
      border-radius: 12px;
      background: white;
      border: 3px solid #000000;
      position: relative;
      display: inline-block;
    }

    canvas {
      display: block;
      max-width: 100%;
      height: auto;
      border: 2px solid #333333;
    }

    .share-row {
      display: flex;
      gap: 12px;
      margin-top: 8px;
      align-items: center;
      flex-wrap: wrap;
    }

    .share-row input {
      flex: 1 1 220px;
      padding: 6px 10px;
      border: 1px solid rgba(0, 0, 0, 0.15);
      border-radius: 6px;
      font-size: 14px;
      background: white;
      color: inherit;
    }

    .digit-panel {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 12px;
    }

    .digit-panel button {
      width: 40px;
      text-align: center;
    }

    .digit-panel button[data-digit="0"] {
      flex: 1 0 80px;
    }

    .steps-container {
      margin-top: 24px;
      display: flex;
      flex-direction: column;
      gap: 18px;
    }

    .step-card {
      background: white;
      border: 3px solid #000000;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .step-card h2 {
      margin: 0 0 12px;
      font-size: 20px;
      color: #333;
    }

    .step-description {
      margin: 12px 0;
      padding: 12px;
      background: #f8f9fa;
      border-radius: 6px;
      border-left: 4px solid #0d6efd;
      font-size: 14px;
      line-height: 1.6;
      color: #495057;
    }

    .step-canvas-wrapper {
      margin: 16px 0;
      display: inline-block;
      border: 3px solid #000000;
      border-radius: 8px;
      padding: 10px;
      background: white;
    }

    .step-canvas-wrapper canvas {
      border: 2px solid #333333;
    }

    .status {
      margin-top: 12px;
      padding: 8px 12px;
      background: #e7f3ff;
      border-radius: 6px;
      font-size: 14px;
      color: #004085;
    }

    footer {
      margin-top: 32px;
      padding: 16px;
      background: #f8f9fa;
      border-radius: 8px;
      font-size: 14px;
      color: #6c757d;
    }

    @media print {
      .toolbar,
      .share-row,
      textarea,
      label,
      footer,
      .status {
        display: none;
      }

      .layout {
        grid-template-columns: 1fr;
        gap: 12px;
      }

      .board-wrapper,
      .step-card,
      .step-canvas-wrapper {
        border: 3px solid #000;
        page-break-inside: avoid;
      }

      .step-card {
        margin-bottom: 20px;
      }
    }

    @media (max-width: 980px) {
      body {
        padding: 16px;
      }

      .layout {
        grid-template-columns: 1fr;
      }
    }
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
        <button id="btn-edit">进入编辑模式</button>
        <button id="btn-step">执行单步</button>
        <button id="btn-solve">自动求解</button>
        <button id="btn-step-clear">清空解析记录</button>
        <button id="btn-print">打印</button>
      </div>

      <div class="board-wrapper">
        <canvas id="board" width="666" height="666">Canvas not supported.</canvas>
      </div>

      <div class="digit-panel" id="digit-panel" hidden>
        <button data-digit="1">1</button>
        <button data-digit="2">2</button>
        <button data-digit="3">3</button>
        <button data-digit="4">4</button>
        <button data-digit="5">5</button>
        <button data-digit="6">6</button>
        <button data-digit="7">7</button>
        <button data-digit="8">8</button>
        <button data-digit="9">9</button>
        <button data-digit="0">清空</button>
      </div>

      <div class="share-row">
        <button id="btn-share">生成分享链接</button>
        <input id="share-link" type="text" readonly placeholder="链接将显示在这里">
      </div>

      <p class="status" id="status-text">编辑模式：可以点击棋盘录入题目。</p>
    </section>

    <section>
      <label for="puzzle-input">批量导入（支持 0/ . 表示空，忽略非数字字符）：</label>
      <textarea id="puzzle-input" placeholder="例如：530070000600195000098000060..."><?php echo htmlspecialchars($puzzleInput); ?></textarea>
      <div class="toolbar" style="margin-top: 12px;">
        <button id="btn-apply">应用到棋盘</button>
        <button id="btn-export">导出当前棋盘</button>
      </div>

      <div class="steps-container" id="steps"></div>
    </section>
  </div>

  <footer>
    提示：点击棋盘中的格子以选择单元，再使用下方数字面板填入数字。执行单步后将自动切换到演示模式，如需修改题目请点击"进入编辑模式"重新调整。
  </footer>

  <script>
    (function () {
      // 常量定义（参考 test.js）
      const CHAR_W = 14;
      const CHAR_H = 14;
      const HALF_CHAR_W = Math.floor(CHAR_W / 2);
      const HALF_CHAR_H = Math.floor(CHAR_H / 2);
      const CELL_W = 3 * CHAR_W;
      const CELL_H = 3 * CHAR_H;
      const PUZZLE_W = 9 * (1 + CELL_W);
      const PUZZLE_H = 9 * (1 + CELL_H);

      // 全局变量
      let p = [];
      let originalPuzzle = [];
      let candidate = [];
      let edit = true;
      let p2 = [];
      let sharelink;
      let tCOL = [], tROW = [], tBOX = [];
      
      // 预计算行列宫索引
      for (let i = 0; i < 81; i++) {
        tCOL[i] = i % 9;
        tROW[i] = Math.floor(i / 9);
        tBOX[i] = 3 * Math.floor(tROW[i] / 3) + Math.floor(tCOL[i] / 3);
      }

      // 技术名称映射
      const tech = {
        NakedSingle: '显性单数 (Naked Single)',
        HiddenSingle: '隐性单数 (Hidden Single)',
        Claiming: '占位排除 (Claiming)',
        Pointing: '定向排除 (Pointing)',
        NakedSubset: '显性子集 (Naked Subset)',
        HiddenSubset: '隐性子集 (Hidden Subset)',
        X_Wing: 'X 翼 (X-Wing)',
        XY_Wing: 'XY 翼 (XY-Wing)',
        W_Wing: 'W 翼 (W-Wing)',
        XYZ_Wing: 'XYZ 翼 (XYZ-Wing)',
        X_Chains: 'X 链 (X-Chains)',
        XY_Chains: 'XY 链 (XY-Chains)'
      };

      let hs_ns = "2";
      let isSolverAll = false;
      let msg = '';
      let stepDescription = '';
      let initialPuzzleSaved = false;

      // 辅助函数
      function COL(i) { return tCOL[i]; }
      function ROW(i) { return tROW[i]; }
      function BOX(i) { return tBOX[i]; }

      function getCoordStr(i) {
        return "(" + (COL(i) + 1) + "," + (ROW(i) + 1) + ")";
      }

      function n2b(n) { return 1 << (n - 1); }
      function bc(n) {
        let c = 0;
        for (let i = 0; i < 9; i++) {
          if (n & (1 << i)) c += 1;
        }
        return c;
      }
      function b2n(mask) {
        for (let i = 0; i < 9; i++) {
          if (mask & (1 << i)) return 1 + i;
        }
        return 0;
      }

      function getIdxFromColRow(col, row) {
        return 9 * row + col;
      }

      function getIdxFromBoxIdx(box, i) {
        const bcol = box % 3;
        const brow = Math.floor(box / 3);
        const ccol = i % 3;
        const crow = Math.floor(i / 3);
        return 9 * (3 * brow + crow) + 3 * bcol + ccol;
      }

      function isSolved(i) {
        return p[i] !== 0;
      }

      function isOriginal(i) {
        return originalPuzzle[i] !== 0;
      }

      function isSingle(candidateMask) {
        let c = 0, n;
        for (let i = 0; i < 9; i++) {
          if (candidateMask & (1 << i)) {
            c += 1;
            n = i + 1;
          }
        }
        if (c === 1) return n;
        else return 0;
      }

      function getCandidateListOfBox(box, c, idx) {
        for (let cell = 0; cell < 9; cell++) {
          const i = getIdxFromBoxIdx(box, cell);
          c[cell] = isSolved(i) ? 0 : candidate[i];
          idx[cell] = i;
        }
      }

      function getCandidateListOfCol(col, c, idx) {
        for (let row = 0; row < 9; row++) {
          const i = getIdxFromColRow(col, row);
          c[row] = isSolved(i) ? 0 : candidate[i];
          idx[row] = i;
        }
      }

      function getCandidateListOfRow(row, c, idx) {
        for (let col = 0; col < 9; col++) {
          const i = getIdxFromColRow(col, row);
          c[col] = isSolved(i) ? 0 : candidate[i];
          idx[col] = i;
        }
      }

      function getCandidateCountOfList(candidateList, n, cell) {
        let c = 0;
        const mask = n2b(n);
        cell.length = 0;
        for (let i = 0; i < 9; i++) {
          if (candidateList[i] & mask) {
            cell[c++] = i;
          }
        }
        return c;
      }

      function initCandidates() {
        for (let row = 0; row < 9; row++) {
          for (let col = 0; col < 9; col++) {
            const i = getIdxFromColRow(col, row);
            if (!isSolved(i)) {
              candidate[i] = 0x1ff;
            } else {
              candidate[i] = n2b(p[i]);
            }
          }
        }
        updateCandidates();
      }

      function updateCandidates() {
        for (let box = 0; box < 9; box++) {
          for (let cell = 0; cell < 9; cell++) {
            const index = getIdxFromBoxIdx(box, cell);
            if (!isSolved(index)) continue;
            const n = p[index];
            for (let i = 0; i < 9; i++) {
              if (i !== cell) {
                candidate[getIdxFromBoxIdx(box, i)] &= ~n2b(n);
              }
            }
          }
        }

        for (let col = 0; col < 9; col++) {
          for (let row = 0; row < 9; row++) {
            const index = getIdxFromColRow(col, row);
            if (!isSolved(index)) continue;
            const n = p[index];
            for (let i = 0; i < 9; i++) {
              if (i !== row) {
                candidate[getIdxFromColRow(col, i)] &= ~n2b(n);
              }
            }
          }
        }

        for (let row = 0; row < 9; row++) {
          for (let col = 0; col < 9; col++) {
            const index = getIdxFromColRow(col, row);
            if (!isSolved(index)) continue;
            const n = p[index];
            for (let i = 0; i < 9; i++) {
              if (i !== col) {
                candidate[getIdxFromColRow(i, row)] &= ~n2b(n);
              }
            }
          }
        }
      }

      // 高亮数组
      let ht1 = [];
      let ht2 = [];
      let ht3 = [];
      let ht4 = [];
      let htMask = [];

      function fillCell(ctx, x, y, color) {
        ctx.fillStyle = color;
        ctx.fillRect(x, y, CELL_W + 1, CELL_H + 1);
        ctx.fillStyle = 'Black';
      }

      function renderPuzzle(name) {
        const c = document.getElementById(name);
        if (!c) return;
        const ctx = c.getContext('2d');
        ctx.lineWidth = 1;
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, PUZZLE_W, PUZZLE_H);
        ctx.fillStyle = 'Black';

        for (let i = 0; i < 81; i++) {
          const x = COL(i) * (1 + CELL_W);
          const y = ROW(i) * (1 + CELL_H);
          if (ht1.indexOf(i) !== -1) {
            fillCell(ctx, x, y, '#B6FF00');
          } else if (ht2.indexOf(i) !== -1) {
            fillCell(ctx, x, y, '#F8FF90');
          }

          const cx = x + Math.floor(CHAR_W / 2);
          const cy = y + Math.floor(CHAR_H / 2) + 1;

          if (p[i] !== 0) {
            ctx.font = '30px Arial';
            // 根据是否为原题数字设置颜色
            if (isOriginal(i)) {
              ctx.fillStyle = '#0066cc'; // 原题数字：蓝色
            } else {
              ctx.fillStyle = '#cc6600'; // 解出数字：橙色
            }
            ctx.fillText(p[i], cx + CHAR_W, cy + CHAR_H);
            ctx.fillStyle = 'Black';
          } else {
            ctx.font = CHAR_H + 'px sans-serif';
            ctx.fillStyle = '#999999'; // 候选数字：浅灰色
            for (let j = 0; j < 9; j++) {
              if (candidate[i] & n2b(1 + j)) {
                ctx.fillText(1 + j, cx + (j % 3) * CHAR_W, cy + Math.floor(j / 3) * CHAR_H);
              }
            }
            const ht = ht3.indexOf(i);
            if (ht !== -1 && htMask[ht] !== 0) {
              ctx.fillStyle = 'Red';
              for (let j = 0; j < 9; j++) {
                if (htMask[ht] & n2b(1 + j)) {
                  const cxx = cx + (j % 3) * CHAR_W;
                  const cyy = cy + Math.floor(j / 3) * CHAR_H;
                  ctx.fillText(1 + j, cxx, cyy);
                  ctx.beginPath();
                  ctx.moveTo(cxx - HALF_CHAR_W, cyy - HALF_CHAR_H);
                  ctx.lineTo(cxx + HALF_CHAR_W, cyy + HALF_CHAR_H);
                  ctx.stroke();
                }
              }
              ctx.fillStyle = 'Black';
            }
          }
        }

        // 绘制网格线
        for (let i = 1; i < 9; i++) {
          if (i % 3 === 0) {
            ctx.strokeStyle = 'Black';
          } else {
            ctx.strokeStyle = 'LightGray';
          }
          const x = i * (1 + CELL_W);
          const y = i * (1 + CELL_H);
          ctx.beginPath();
          ctx.moveTo(x, 0);
          ctx.lineTo(x, PUZZLE_H);
          ctx.moveTo(0, y);
          ctx.lineTo(PUZZLE_W, y);
          ctx.stroke();
        }

        // 绘制链式连接
        ctx.strokeStyle = 'Red';
        for (let i = 0; i < ht4.length; i += 3) {
          const a = ht4[i];
          const b = ht4[i + 1];
          const n = ht4[i + 2] - 1;
          const xa = COL(a) * (1 + CELL_W);
          const ya = ROW(a) * (1 + CELL_H);
          const cxa = xa + (n % 3) * CHAR_W;
          const cya = ya + Math.floor(n / 3) * CHAR_H;
          const xb = COL(b) * (1 + CELL_W);
          const yb = ROW(b) * (1 + CELL_H);
          const cxb = xb + (n % 3) * CHAR_W;
          const cyb = yb + Math.floor(n / 3) * CHAR_H;
          ctx.beginPath();
          ctx.rect(cxa, cya, CHAR_W, CHAR_H);
          ctx.rect(cxb, cyb, CHAR_W, CHAR_H);
          ctx.moveTo(cxa + HALF_CHAR_W, cya + HALF_CHAR_H);
          ctx.lineTo(cxb + HALF_CHAR_W, cyb + HALF_CHAR_H);
          ctx.stroke();
        }
        ctx.strokeStyle = 'Black';

        // 清空高亮数组
        ht1 = [];
        ht2 = [];
        ht3 = [];
        ht4 = [];
        htMask = [];
      }

      function saveInitialState() {
        if (!initialPuzzleSaved) {
          originalPuzzle = p.slice(0);
          addStep(0, "初始题目状态");
          initialPuzzleSaved = true;
        }
      }

      function addStep(round, description) {
        const name = 'c' + round;
        const steps = document.getElementById('steps');
        const card = document.createElement('div');
        card.className = 'step-card';
        
        const h2 = document.createElement('h2');
        h2.textContent = '步骤 ' + round + (msg ? ': ' + msg : '');
        card.appendChild(h2);

        if (description) {
          const descDiv = document.createElement('div');
          descDiv.className = 'step-description';
          descDiv.textContent = description;
          card.appendChild(descDiv);
        }

        const canvasWrapper = document.createElement('div');
        canvasWrapper.className = 'step-canvas-wrapper';
        const c = document.createElement('canvas');
        c.setAttribute('id', name);
        c.setAttribute('width', PUZZLE_W);
        c.setAttribute('height', PUZZLE_H);
        canvasWrapper.appendChild(c);
        card.appendChild(canvasWrapper);

        steps.appendChild(card);
        renderPuzzle(name);
      }

      // 解独模式
      const pattern = [];
      let msg = '';

      function findSingle(c, idx, unitType, unitIndex) {
        const cell = [];
        for (let n = 1; n <= 9; n++) {
          if (getCandidateCountOfList(c, n, cell) !== 1) continue;
          const i = idx[cell[0]];
          p[i] = n;
          candidate[i] = n2b(n);
          ht1.push(i);
          
          const unitName = unitType === "box" ? `第${unitIndex + 1}宫` : 
                          unitType === "row" ? `第${unitIndex + 1}行` : 
                          `第${unitIndex + 1}列`;
          stepDescription = `${unitName}中，数字${n}只能出现在${getCoordStr(i)}，因此${getCoordStr(i)}=${n}。`;
          return true;
        }
        return false;
      }

      function p_findSingle() {
        if (hs_ns === "2") {
          for (let i = 0; i < 81; i++) {
            if (isSolved(i)) continue;
            const n = isSingle(candidate[i]);
            if (n === 0) continue;
            p[i] = n;
            candidate[i] = n2b(n);
            ht1.push(i);
            msg = tech.NakedSingle;
            stepDescription = `坐标${getCoordStr(i)}的候选数只有${n}，因此${getCoordStr(i)}=${n}。`;
            return true;
          }
        }

        msg = tech.HiddenSingle;
        const c = [];
        const idx = [];
        for (let i = 0; i < 9; i++) {
          getCandidateListOfBox(i, c, idx);
          if (findSingle(c, idx, "box", i)) {
            ht2 = idx.slice();
            return true;
          }
          getCandidateListOfCol(i, c, idx);
          if (findSingle(c, idx, "col", i)) {
            ht2 = idx.slice();
            return true;
          }
          getCandidateListOfRow(i, c, idx);
          if (findSingle(c, idx, "row", i)) {
            ht2 = idx.slice();
            return true;
          }
        }

        if (hs_ns === "1") {
          for (let i = 0; i < 81; i++) {
            if (isSolved(i)) continue;
            const n = isSingle(candidate[i]);
            if (n === 0) continue;
            p[i] = n;
            candidate[i] = n2b(n);
            ht1.push(i);
            msg = tech.NakedSingle;
            stepDescription = `坐标${getCoordStr(i)}的候选数只有${n}，因此${getCoordStr(i)}=${n}。`;
            return true;
          }
        }
        return false;
      }

      function findClaiming(c, idx, unitType, unitIndex) {
        const cell = [];
        for (let n = 1; n <= 9; n++) {
          const count = getCandidateCountOfList(c, n, cell);
          if (count !== 2 && count !== 3) continue;
          if (count === 2) cell[2] = cell[0];
          if (BOX(idx[cell[0]]) !== BOX(idx[cell[1]]) || BOX(idx[cell[0]]) !== BOX(idx[cell[2]])) continue;
          
          const c2 = [];
          const idx2 = [];
          const box = BOX(idx[cell[0]]);
          getCandidateListOfBox(box, c2, idx2);
          let changed = false;
          const mask = n2b(n);
          const excludedCells = [];
          for (let i = 0; i < 9; i++) {
            const index = idx2[i];
            if (isSolved(index)) continue;
            let again = false;
            for (let j = 0; j < count; j++) {
              if (index === idx[cell[j]]) {
                again = true;
                break;
              }
            }
            if (again) continue;
            if ((candidate[index] & mask) === 0) continue;
            candidate[index] &= ~mask;
            changed = true;
            excludedCells.push(index);
            ht2 = idx.slice();
            ht3.push(index);
            htMask.push(mask);
          }
          if (!changed) continue;
          for (let i = 0; i < count; i++) {
            ht1.push(idx[cell[i]]);
          }
          msg = tech.Claiming;
          const unitName = unitType === "row" ? `第${unitIndex + 1}行` : `第${unitIndex + 1}列`;
          const boxName = `第${box + 1}宫`;
          const cellsStr = Array.from({ length: count }, (_, j) => getCoordStr(idx[cell[j]])).join("和");
          const excludedStr = excludedCells.map(i => getCoordStr(i)).join("、");
          stepDescription = `${unitName}中，数字${n}的候选位置${cellsStr}都在${boxName}内，因此${boxName}其他位置${excludedStr}排除候选值${n}。`;
          return true;
        }
        return false;
      }

      function p_findClaiming() {
        const c = [];
        const idx = [];
        for (let i = 0; i < 9; i++) {
          getCandidateListOfCol(i, c, idx);
          if (findClaiming(c, idx, "col", i)) return true;
          getCandidateListOfRow(i, c, idx);
          if (findClaiming(c, idx, "row", i)) return true;
        }
        return false;
      }

      function p_findPointing() {
        const c = [];
        const idx = [];
        const cell = [];
        for (let box = 0; box < 9; box++) {
          getCandidateListOfBox(box, c, idx);
          for (let n = 1; n <= 9; n++) {
            const count = getCandidateCountOfList(c, n, cell);
            if (count !== 2 && count !== 3) continue;
            const col = [];
            const row = [];
            for (let i = 0; i < count; i++) {
              const index = idx[cell[i]];
              col[i] = COL(index) % 3;
              row[i] = ROW(index) % 3;
            }
            if (count === 2) {
              col[2] = col[0];
              row[2] = row[0];
            }
            const c2 = [];
            const idx2 = [];
            let unitType = "";
            let unitIndex = -1;
            if (col[0] === col[1] && col[0] === col[2]) {
              unitIndex = COL(idx[cell[0]]);
              getCandidateListOfCol(unitIndex, c2, idx2);
              unitType = "col";
            } else if (row[0] === row[1] && row[0] === row[2]) {
              unitIndex = ROW(idx[cell[0]]);
              getCandidateListOfRow(unitIndex, c2, idx2);
              unitType = "row";
            } else {
              continue;
            }
            let changed = false;
            const mask = n2b(n);
            const excludedCells = [];
            for (let i = 0; i < 9; i++) {
              const index = idx2[i];
              if (isSolved(index)) continue;
              let again = false;
              for (let j = 0; j < count; j++) {
                if (index === idx[cell[j]]) {
                  again = true;
                  break;
                }
              }
              if (again) continue;
              if ((candidate[index] & mask) === 0) continue;
              candidate[index] &= ~mask;
              changed = true;
              excludedCells.push(index);
              ht2 = idx.slice();
              ht3.push(index);
              htMask.push(mask);
            }
            if (!changed) continue;
            for (let i = 0; i < count; i++) {
              ht1.push(idx[cell[i]]);
            }
            msg = tech.Pointing;
            const boxName = `第${box + 1}宫`;
            const unitName = unitType === "row" ? `第${unitIndex + 1}行` : `第${unitIndex + 1}列`;
            const cellsStr = Array.from({ length: count }, (_, j) => getCoordStr(idx[cell[j]])).join("和");
            const excludedStr = excludedCells.map(i => getCoordStr(i)).join("、");
            stepDescription = `${boxName}中，数字${n}的候选位置${cellsStr}都在${unitName}上，因此${unitName}其他位置${excludedStr}排除候选值${n}。`;
            return true;
          }
        }
        return false;
      }

      function findNakedSet(c, idx, n, unitType, unitIndex) {
        const pos = [];
        for (let mask = 0; mask < 0x1ff; mask++) {
          if (bc(mask) !== n) continue;
          let i2 = 0;
          for (let j = 0; j < 9; j++) {
            if (c[j] && (c[j] & ~mask) === 0) {
              pos[i2] = idx[j];
              i2++;
            }
          }
          if (i2 !== n) continue;
          let changed = false;
          const excludedValues = [];
          const excludedCells = [];
          for (let j = 0; j < 9; j++) {
            const index = idx[j];
            if (isSolved(index)) continue;
            let again = false;
            for (let k = 0; k < n; k++) {
              if (index === pos[k]) {
                again = true;
                break;
              }
            }
            if (again) continue;
            const removed = candidate[idx[j]] & mask;
            if (removed === 0) continue;
            htMask.push(removed);
            ht3.push(idx[j]);
            candidate[idx[j]] &= ~mask;
            changed = true;
            excludedCells.push(index);
            for (let bit = 0; bit < 9; bit++) {
              if (removed & (1 << bit)) {
                excludedValues.push(bit + 1);
              }
            }
          }
          if (!changed) continue;
          for (let j = 0; j < n; j++) {
            ht1.push(pos[j]);
          }
          ht2 = idx.slice();
          msg = tech.NakedSubset;
          const unitName = unitType === "box" ? `第${unitIndex + 1}宫` : 
                          unitType === "row" ? `第${unitIndex + 1}行` : 
                          `第${unitIndex + 1}列`;
          const cellsStr = pos.map(i => getCoordStr(i)).join("和");
          const valuesStr = Array.from({ length: 9 }, (_, i) => (mask & (1 << i)) ? i + 1 : null).filter(v => v !== null).join(",");
          const excludedStr = excludedCells.map(i => getCoordStr(i)).join("、");
          const excludedValuesStr = [...new Set(excludedValues)].sort((a, b) => a - b).join("、");
          stepDescription = `${unitName}中，坐标${cellsStr}形成裸${n === 2 ? "数对" : n === 3 ? "数组" : "数组"}[${valuesStr}]，删除${excludedStr}的相同候选值${excludedValuesStr}。`;
          return true;
        }
        return false;
      }

      function p_findSubset() {
        const c = [];
        const idx = [];
        for (let n = 2; n <= 4; n++) {
          for (let i = 0; i < 9; i++) {
            getCandidateListOfBox(i, c, idx);
            if (findNakedSet(c, idx, n, "box", i)) return true;
            getCandidateListOfCol(i, c, idx);
            if (findNakedSet(c, idx, n, "col", i)) return true;
            getCandidateListOfRow(i, c, idx);
            if (findNakedSet(c, idx, n, "row", i)) return true;
          }
        }
        return false;
      }

      // 注册解独模式
      pattern.push(p_findSingle);
      pattern.push(p_findClaiming);
      pattern.push(p_findPointing);
      pattern.push(p_findSubset);

      function solve() {
        sharelink.style.display = 'none';
        if (!edit) return;
        
        saveInitialState();
        
        p2 = p.slice(0);
        let round = 1;
        while (true) {
          let over = true;
          for (let i = 0; i < pattern.length; i++) {
            if (pattern[i]()) {
              updateCandidates();
              addStep(round++, stepDescription);
              if (isSolverAll) {
                over = false;
              } else if (msg === tech.NakedSingle || msg === tech.HiddenSingle) {
                over = true;
              } else {
                over = false;
              }
              edit = false;
              break;
            }
          }
          if (over) break;
        }
        
        let _is_solved = true;
        for (let i = 0; i < 81; i++) {
          if (!isSolved(i)) {
            _is_solved = false;
            break;
          }
        }
        
        if (_is_solved) {
          document.getElementById('status-text').textContent = '数独已完全求解！';
        } else {
          document.getElementById('status-text').textContent = '无法继续求解，可能需要更高级的技巧。';
        }
      }

      function resetPuzzle() {
        edit = true;
        sharelink.style.display = 'none';
        const s = document.getElementById('steps');
        s.innerHTML = '';
        for (let i = 0; i < 81; i++) {
          p[i] = 0;
        }
        originalPuzzle = [];
        initialPuzzleSaved = false;
        initCandidates();
        renderPuzzle('board');
        document.getElementById('status-text').textContent = '编辑模式：可以点击棋盘录入题目。';
      }

      function applyExample() {
        const example = "530070000600195000098000060800060003400803001700020006060000280000419005000080079";
        document.getElementById('puzzle-input').value = example;
        applyPuzzle();
      }

      function applyPuzzle() {
        const text = document.getElementById('puzzle-input').value;
        const cleaned = text.replace(/[^0-9.]/g, '');
        for (let i = 0; i < 81 && i < cleaned.length; i++) {
          const ch = cleaned[i];
          p[i] = (ch === '.' || ch === '0') ? 0 : parseInt(ch);
        }
        originalPuzzle = p.slice(0);
        initialPuzzleSaved = false;
        initCandidates();
        renderPuzzle('board');
        document.getElementById('status-text').textContent = '题目已应用，可以开始求解。';
      }

      function exportPuzzleText() {
        let text = '';
        for (let i = 0; i < 81; i++) {
          text += p[i] === 0 ? '.' : p[i];
        }
        document.getElementById('puzzle-input').value = text;
      }

      function generateShareLink() {
        if (edit) {
          p2 = p.slice(0);
        }
        let link = location.href.split("?")[0].split("#")[0] + '?p=';
        for (let i = 0; i < 81; i++) {
          link += p2[i];
          if (i < 80) link += ',';
        }
        if (!edit) {
          link += '&s=1';
        }
        sharelink.style.display = 'block';
        sharelink.value = link;
      }

      function toggleEditMode() {
        edit = !edit;
        const digitPanel = document.getElementById('digit-panel');
        if (edit) {
          digitPanel.hidden = false;
          document.getElementById('status-text').textContent = '编辑模式：可以点击棋盘录入题目。';
        } else {
          digitPanel.hidden = true;
          document.getElementById('status-text').textContent = '演示模式：已禁用编辑功能。';
        }
      }

      function printPuzzle() {
        window.print();
      }

      // 初始化
      function init() {
        // 从 PHP 传入的初始题目
        const initialPuzzle = <?php echo $puzzleJson; ?>;
        for (let i = 0; i < 81; i++) {
          p[i] = initialPuzzle[i] || 0;
        }
        originalPuzzle = p.slice(0);
        initCandidates();
        
        const board = document.getElementById('board');
        board.setAttribute('width', PUZZLE_W);
        board.setAttribute('height', PUZZLE_H);
        renderPuzzle('board');

        // 事件监听
        board.onmousedown = function(e) {
          sharelink.style.display = 'none';
          if (!edit || e.button === 2) return;
          const col = Math.floor(e.offsetX / (1 + CELL_W));
          const row = Math.floor(e.offsetY / (1 + CELL_H));
          const i = getIdxFromColRow(col, row);
          if (p[i] !== 0) {
            p[i] = 0;
            originalPuzzle[i] = 0;
            initCandidates();
            renderPuzzle('board');
            return false;
          }
          const chcol = Math.floor((e.offsetX - col * (1 + CELL_W)) / CHAR_W);
          const chrow = Math.floor((e.offsetY - row * (1 + CELL_H)) / CHAR_H);
          const j = chcol + 3 * chrow;
          if (candidate[i] & n2b(1 + j)) {
            p[i] = 1 + j;
            originalPuzzle[i] = 1 + j;
            initCandidates();
            renderPuzzle('board');
          }
        };

        document.getElementById('btn-reset').onclick = resetPuzzle;
        document.getElementById('btn-example').onclick = applyExample;
        document.getElementById('btn-edit').onclick = toggleEditMode;
        document.getElementById('btn-step').onclick = function() {
          saveInitialState();
          let found = false;
          for (let i = 0; i < pattern.length; i++) {
            if (pattern[i]()) {
              updateCandidates();
              const round = document.getElementById('steps').children.length;
              addStep(round, stepDescription);
              edit = false;
              document.getElementById('digit-panel').hidden = true;
              found = true;
              break;
            }
          }
          if (!found) {
            document.getElementById('status-text').textContent = '无法找到下一步解法。';
          }
        };
        document.getElementById('btn-solve').onclick = solve;
        document.getElementById('btn-step-clear').onclick = function() {
          document.getElementById('steps').innerHTML = '';
          initialPuzzleSaved = false;
        };
        document.getElementById('btn-print').onclick = printPuzzle;
        document.getElementById('btn-share').onclick = generateShareLink;
        document.getElementById('btn-apply').onclick = applyPuzzle;
        document.getElementById('btn-export').onclick = exportPuzzleText;

        sharelink = document.getElementById('share-link');
        sharelink.style.display = 'none';
        sharelink.style.width = '400px';
        sharelink.style.height = '2em';
        sharelink.style.border = '1px solid #666';

        const digitPanel = document.getElementById('digit-panel');
        digitPanel.querySelectorAll('button').forEach(btn => {
          btn.onclick = function() {
            const digit = parseInt(btn.getAttribute('data-digit'));
            // 这里可以添加选择单元格的逻辑
          };
        });
      }

      // 页面加载完成后初始化
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
      } else {
        init();
      }
    })();
  </script>
</body>
</html>
