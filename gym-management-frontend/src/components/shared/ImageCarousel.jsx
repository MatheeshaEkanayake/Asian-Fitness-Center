import { useEffect, useState } from 'react'

export default function ImageCarousel({ images, intervalMs = 5000, className = '' }) {
  const [index, setIndex] = useState(0)

  useEffect(() => {
    if (images.length < 2) return
    const timer = setInterval(() => {
      setIndex((prev) => (prev + 1) % images.length)
    }, intervalMs)
    return () => clearInterval(timer)
  }, [images.length, intervalMs])

  return (
    <div className={`relative w-full h-full overflow-hidden ${className}`}>
      {images.map((image, i) => (
        <img
          key={image.src}
          src={image.src}
          alt={image.alt}
          className="absolute inset-0 w-full h-full object-contain transition-transform duration-700 ease-in-out"
          style={{ transform: `translateX(${(i - index) * 100}%)` }}
        />
      ))}
      {images.length > 1 && (
        <div className="absolute bottom-0 left-1/2 -translate-x-1/2 flex gap-1.5">
          {images.map((image, i) => (
            <span
              key={image.src}
              className={`h-1.5 rounded-full transition-all ${
                i === index ? 'w-5 bg-white' : 'w-1.5 bg-white/40'
              }`}
            />
          ))}
        </div>
      )}
    </div>
  )
}
