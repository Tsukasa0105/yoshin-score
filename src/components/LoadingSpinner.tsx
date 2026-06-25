import './LoadingSpinner.css'

interface LoadingSpinnerProps {
  fileName: string
}

export default function LoadingSpinner({ fileName }: LoadingSpinnerProps) {
  return (
    <div className="loading-container">
      <div className="spinner-ring" aria-hidden="true">
        <div /><div /><div /><div />
      </div>
      <p className="loading-title">AIが決算書を解析中です</p>
      {fileName && <p className="loading-file">📄 {fileName}</p>}
      <p className="loading-sub">財務データを読み取り、スコアリングを行っています<br />しばらくお待ちください（通常30〜60秒）</p>
    </div>
  )
}
