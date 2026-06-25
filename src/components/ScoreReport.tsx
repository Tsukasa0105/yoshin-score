import './ScoreReport.css'

interface ScoreReportProps {
  html: string
  fileName: string
}

export default function ScoreReport({ html, fileName }: ScoreReportProps) {
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
        className="report-body"
        dangerouslySetInnerHTML={{ __html: html }}
      />
    </div>
  )
}
