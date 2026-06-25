import { useState, useEffect } from 'react'
import './History.css'

export interface HistoryItem {
  id: string
  timestamp: string
  date: string
  company_name: string
  file_name: string
  score: string
  judgment: string
  judgment_class: string
}

interface HistoryProps {
  onViewResult: (id: string) => void
  onClose: () => void
}

export default function History({ onViewResult, onClose }: HistoryProps) {
  const [items, setItems] = useState<HistoryItem[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    fetch('/api/history.php')
      .then(r => r.json())
      .then((data: HistoryItem[]) => setItems(data))
      .catch(() => setError('履歴の読み込みに失敗しました'))
      .finally(() => setLoading(false))
  }, [])

  const deleteItem = async (id: string, e: React.MouseEvent) => {
    e.stopPropagation()
    if (!window.confirm('この結果を削除しますか？')) return
    const fd = new FormData()
    fd.append('action', 'delete')
    await fetch(`/api/history.php?id=${id}`, { method: 'POST', body: fd })
    setItems(prev => prev.filter(item => item.id !== id))
  }

  return (
    <div className="hist-overlay" onClick={e => { if (e.target === e.currentTarget) onClose() }}>
      <div className="hist-panel">
        <div className="hist-header">
          <h2>解析履歴</h2>
          <button className="hist-close" onClick={onClose} aria-label="閉じる">✕</button>
        </div>

        <div className="hist-body">
          {loading && <p className="hist-state">読み込み中...</p>}
          {error   && <p className="hist-state hist-error">{error}</p>}
          {!loading && !error && items.length === 0 && (
            <div className="hist-empty">
              <p>まだ解析履歴がありません</p>
              <p>決算書をアップロードすると、ここに結果が蓄積されます。</p>
            </div>
          )}
          {!loading && items.map(item => (
            <div key={item.id} className="hist-item" onClick={() => onViewResult(item.id)}>
              <div className="hist-item-body">
                <div className="hist-company">{item.company_name}</div>
                <div className="hist-meta">
                  <span className="hist-date">{item.date}</span>
                  <span className="hist-file">{item.file_name}</span>
                </div>
                <div className="hist-result">
                  <span className={`hist-badge ${item.judgment_class}`}>{item.judgment}</span>
                  <span className="hist-score">{item.score}</span>
                </div>
              </div>
              <button
                className="hist-delete"
                onClick={e => deleteItem(item.id, e)}
                aria-label="削除"
              >
                🗑
              </button>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}
