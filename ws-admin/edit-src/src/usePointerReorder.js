import { useCallback, useRef, useState } from 'react';

// Riordino con Pointer Events: funziona con mouse E touch (l'HTML5 drag&drop
// nativo non parte col dito). Convenzione DOM:
//  - il contenitore ha [data-reorder-root]
//  - ogni elemento trascinabile ha [data-reorder-index="<i>"]
//  - la maniglia chiama onHandlePointerDown(e, i) e ha css touch-action: none
// onReorder(from, insertIndex) riceve l'indice di inserimento (0..length).
export function usePointerReorder(onReorder, { axis = 'y' } = {}) {
  const [dragIndex, setDragIndex] = useState(null);
  const [overIndex, setOverIndex] = useState(null);
  const st = useRef({ from: null, to: null });

  const insertionAt = (root, x, y) => {
    const items = [...root.querySelectorAll('[data-reorder-index]')];
    for (const el of items) {
      const r = el.getBoundingClientRect();
      const before = axis === 'y' ? y < r.top + r.height / 2 : x < r.left + r.width / 2;
      if (before) return Number(el.dataset.reorderIndex);
    }
    return items.length;
  };

  const onHandlePointerDown = useCallback(
    (e, index) => {
      e.preventDefault();
      e.stopPropagation();
      const root = e.currentTarget.closest('[data-reorder-root]');
      if (!root) return;
      st.current = { from: index, to: index };
      setDragIndex(index);
      setOverIndex(index);
      const move = (ev) => {
        const to = insertionAt(root, ev.clientX, ev.clientY);
        st.current.to = to;
        setOverIndex(to);
      };
      const up = () => {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', up);
        window.removeEventListener('pointercancel', up);
        const { from, to } = st.current;
        if (from != null && to != null && to !== from && to !== from + 1) onReorder(from, to);
        setDragIndex(null);
        setOverIndex(null);
      };
      window.addEventListener('pointermove', move);
      window.addEventListener('pointerup', up);
      window.addEventListener('pointercancel', up);
    },
    [onReorder, axis]
  );

  return { dragIndex, overIndex, onHandlePointerDown };
}
