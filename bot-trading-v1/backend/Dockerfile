FROM python:3.11-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# Non-root user
RUN useradd -m botuser && chown -R botuser /app
USER botuser

EXPOSE 8000

# Paper mode / live disabled are the defaults; override only via env + a real
# live adapter. Health at /health.
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
