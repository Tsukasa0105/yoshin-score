import { useEffect, useRef } from 'react'
import './ScoreReport.css'

interface ScoreReportProps {
  html: string
  fileName: string
}

const JUDGMENT_CLASSES: [RegExp, string][] = [
  [/審査NG|足切り/,          'judgment-ng'],
  [/不適格/,                  'judgment-ng'],
  [/課題多/,                  'judgment-warn'],
  [/要検討/,                  'judgment-warn'],
  [/適格/,                    'judgment-ok'],
]

export default function ScoreReport({ html, fileName }: ScoreReportProps) {
  const bodyRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!bodyRef.current) return
    const el = bodyRef.current.querySelector<HTMLElement>('.judgment-result')
    if (!el) return

    for (const [pattern, cls] of JUDGMENT_CLASSES) {
      if (pattern.test(el.textContent ?? '')) {
        el.classList.add(cls)
        break
      }
    }
  }, [html])

  const handlePrint = () => window.print()

  return (
    <div className="report-wrapper">
      <div className="report-toolbar">
        <div className="report-meta">
          <span className="report-icon">✅</span>
          <span>解析完了：<strong>{fileName}</strong></span>
        </div>
        <button className="btn-print" onClick={handlePrint}>
          🖨️ 印刷 / PDF保存
        </button>
      </div>
      <div
        ref={bodyRef}
        className="report-body"
        dangerouslySetInnerHTML={{ __html: html }}
      />
    </div>
  )
}
