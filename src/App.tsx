import { useState, useCallback } from 'react'
import FileUpload from './components/FileUpload'
import ScoreReport from './components/ScoreReport'
import LoadingSpinner from './components/LoadingSpinner'
import './App.css'

function App() {
  const [loading, setLoading] = useState(false)
  const [result, setResult] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [fileName, setFileName] = useState<string>('')

  const handleFileUpload = useCallback(async (file: File) => {
    setLoading(true)
    setResult(null)
    setError(null)
    setFileName(file.name)

    const formData = new FormData()
    formData.append('file', file)

    try {
      const response = await fetch('/api/analyze.php', {
        method: 'POST',
        body: formData,
      })

      const data = await response.json()

      if (!response.ok) {
        throw new Error(data.error || `サーバーエラー (${response.status})`)
      }

      if (!data.html) {
        throw new Error('解析結果が取得できませんでした')
      }

      setResult(data.html)
    } catch (err) {
      setError(err instanceof Error ? err.message : '予期しないエラーが発生しました')
    } finally {
      setLoading(false)
    }
  }, [])

  const handleReset = useCallback(() => {
    setResult(null)
    setError(null)
    setFileName('')
  }, [])

  return (
    <div className="app">
      <header className="app-header">
        <div className="header-inner">
          <div className="brand">
            <span className="brand-logo">📊</span>
            <div>
              <h1>与信スコアリングシステム</h1>
              <p>株式会社セラヴィ｜財務審査AI</p>
            </div>
          </div>
          <span className="header-badge">AI POWERED</span>
        </div>
      </header>

      <main className="app-main">
        <div className="container">
          {!loading && !result && !error && (
            <FileUpload onUpload={handleFileUpload} />
          )}

          {loading && <LoadingSpinner fileName={fileName} />}

          {error && !loading && (
            <div className="error-box">
              <h3>❌ エラーが発生しました</h3>
              <p>{error}</p>
              <button onClick={handleReset}>やり直す</button>
            </div>
          )}

          {result && !loading && (
            <>
              <ScoreReport html={result} fileName={fileName} />
              <div className="retry-section">
                <button className="btn-retry" onClick={handleReset}>
                  ← 別の決算書を解析する
                </button>
              </div>
            </>
          )}
        </div>
      </main>

      <footer className="app-footer">
        <p>© 2025 株式会社セラヴィ. All rights reserved. | 本システムの判定結果は参考情報です。最終判断は専門家にご確認ください。</p>
      </footer>
    </div>
  )
}

export default App
