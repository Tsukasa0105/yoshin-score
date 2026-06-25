import { useState, useCallback, useRef } from 'react'
import './FileUpload.css'

interface FileUploadProps {
  onUpload: (file: File) => void
}

const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'xls', 'xlsx', 'html', 'htm']
const MAX_SIZE = 20 * 1024 * 1024

function validateFile(file: File): string | null {
  if (file.size > MAX_SIZE) return `ファイルサイズが大きすぎます（最大20MB）`
  const ext = file.name.toLowerCase().split('.').pop() ?? ''
  if (!ALLOWED_EXT.includes(ext)) return `対応していないファイル形式です（${file.name}）`
  return null
}

export default function FileUpload({ onUpload }: FileUploadProps) {
  const [isDragging, setIsDragging] = useState(false)
  const [fileError, setFileError] = useState<string | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  const handleFile = useCallback((file: File) => {
    const err = validateFile(file)
    if (err) { setFileError(err); return }
    setFileError(null)
    onUpload(file)
  }, [onUpload])

  const onDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault()
    setIsDragging(false)
    const file = e.dataTransfer.files[0]
    if (file) handleFile(file)
  }, [handleFile])

  const onDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault()
    setIsDragging(true)
  }, [])

  const onDragLeave = useCallback(() => setIsDragging(false), [])

  const onChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (file) handleFile(file)
    e.target.value = ''
  }, [handleFile])

  return (
    <div className="upload-section">
      <div className="upload-intro">
        <h2>決算書をアップロード</h2>
        <p>財務データを自動で読み取り、与信スコアリングを行います</p>
      </div>

      <div className="upload-drop-col">
        <div
          className={`upload-area${isDragging ? ' dragging' : ''}`}
          onDrop={onDrop}
          onDragOver={onDragOver}
          onDragLeave={onDragLeave}
          onClick={() => inputRef.current?.click()}
          role="button"
          tabIndex={0}
          onKeyDown={(e) => e.key === 'Enter' && inputRef.current?.click()}
          aria-label="決算書ファイルをアップロード"
        >
          <svg className="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/>
            <polyline points="16 11 12 7 8 11"/>
            <line x1="12" y1="7" x2="12" y2="17"/>
          </svg>
          <p className="upload-text">ここにファイルをドラッグ＆ドロップ</p>
          <p className="upload-sub">または</p>
          <span className="btn-upload">ファイルを選択</span>
          <input
            ref={inputRef}
            type="file"
            accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.xls,.xlsx,.html,.htm"
            onChange={onChange}
            style={{ display: 'none' }}
          />
        </div>

        {fileError && <p className="file-error">{fileError}</p>}

        <div className="info-card">
          <h4>対応ファイル形式</h4>
          <ul className="format-list">
            <li>PDF</li>
            <li>JPEG / PNG</li>
            <li>WEBP / GIF</li>
            <li>Excel (.xls / .xlsx)</li>
            <li>HTML</li>
          </ul>
          <p className="format-note">最大ファイルサイズ：20MB</p>
        </div>
      </div>

      <div className="info-col">
        <div className="info-card">
          <h4>審査基準（合計50点）</h4>
          <table className="scoring-table">
            <tbody>
              <tr>
                <td>自己資本比率</td>
                <td>20点</td>
              </tr>
              <tr>
                <td>営業利益（直近2期）</td>
                <td>15点</td>
              </tr>
              <tr>
                <td>流動比率</td>
                <td>8点</td>
              </tr>
              <tr>
                <td>有利子負債月商倍率</td>
                <td>7点</td>
              </tr>
              <tr className="scoring-total">
                <td>合計</td>
                <td>50点</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div className="info-card">
          <h4>ご利用上の注意</h4>
          <ul className="notice-list">
            <li>アップロードされたファイルは解析後に削除されます</li>
            <li>判定結果は参考情報です。最終判断は専門家にご確認ください</li>
            <li>決算書は最新2期分が含まれるものを推奨します</li>
            <li>数値が読み取れない場合は「読み取り不可」と表示されます</li>
          </ul>
        </div>
      </div>
    </div>
  )
}
