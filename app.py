import os
import smtplib
import ssl
from email.message import EmailMessage
from flask import Flask, request, jsonify, send_from_directory


def create_app():
    app = Flask(__name__, static_folder=".", static_url_path="")

    required_env = [
        "SMTP_HOST",
        "SMTP_PORT",
        "SMTP_USER",
        "SMTP_PASS",
        "MAIL_FROM",
        "MAIL_TO",
    ]
    missing = [k for k in required_env if not os.environ.get(k)]
    if missing:
        app.logger.warning(
            "Missing SMTP env vars: %s. Emails will fail until provided.", ", ".join(missing)
        )

    def send_mail(subject: str, text: str, html: str, reply_to: str = None):
        host = os.environ.get("SMTP_HOST")
        port = int(os.environ.get("SMTP_PORT", 587))
        user = os.environ.get("SMTP_USER")
        pwd = os.environ.get("SMTP_PASS")
        mail_from = os.environ.get("MAIL_FROM")
        mail_to = os.environ.get("MAIL_TO")

        msg = EmailMessage()
        msg["Subject"] = subject
        msg["From"] = mail_from
        msg["To"] = mail_to
        if reply_to:
            msg["Reply-To"] = reply_to
        msg.set_content(text)
        msg.add_alternative(html, subtype="html")

        context = ssl.create_default_context()
        with smtplib.SMTP_SSL(host, port, context=context) if port == 465 else smtplib.SMTP(
            host, port
        ) as server:
            if port != 465:
                server.starttls(context=context)
            server.login(user, pwd)
            server.send_message(msg)

    @app.route("/")
    def root():
        return send_from_directory(".", "index.html")

    @app.route("/<path:path>")
    def static_files(path):
        # Serve other static files (html, css, js, img, etc.)
        return send_from_directory(".", path)

    @app.post("/api/contact")
    def contact():
        data = request.get_json(force=True, silent=True) or {}
        missing = [k for k in ["name", "email", "subject", "message"] if not data.get(k)]
        if missing:
            return jsonify({"ok": False, "error": f"Champs manquants: {', '.join(missing)}"}), 400

        subject = f"Contact MARGE - {data['subject']}"
        text = (
            f"Nom: {data['name']}\n"
            f"Email: {data['email']}\n"
            f"Sujet: {data['subject']}\n"
            f"Message:\n{data['message']}"
        )
        html = (
            f"<p><strong>Nom:</strong> {data['name']}</p>"
            f"<p><strong>Email:</strong> {data['email']}</p>"
            f"<p><strong>Sujet:</strong> {data['subject']}</p>"
            f"<p><strong>Message:</strong><br>{data['message']}</p>"
        )
        try:
            send_mail(subject, text, html, reply_to=data["email"])
            return jsonify({"ok": True})
        except Exception as err:  # pragma: no cover - runtime SMTP
            app.logger.error("Contact email error: %s", err)
            return jsonify({"ok": False, "error": "Erreur lors de l'envoi du message."}), 500

    @app.post("/api/booking")
    def booking():
        data = request.get_json(force=True, silent=True) or {}
        missing = [k for k in ["name", "email", "service", "date", "details"] if not data.get(k)]
        if missing:
            return jsonify({"ok": False, "error": f"Champs manquants: {', '.join(missing)}"}), 400

        subject = f"Réservation MARGE - {data['service']}"
        text = (
            f"Nom: {data['name']}\n"
            f"Email: {data['email']}\n"
            f"Service: {data['service']}\n"
            f"Date souhaitée: {data['date']}\n"
            f"Détails:\n{data['details']}"
        )
        html = (
            f"<p><strong>Nom:</strong> {data['name']}</p>"
            f"<p><strong>Email:</strong> {data['email']}</p>"
            f"<p><strong>Service:</strong> {data['service']}</p>"
            f"<p><strong>Date souhaitée:</strong> {data['date']}</p>"
            f"<p><strong>Détails:</strong><br>{data['details']}</p>"
        )
        try:
            send_mail(subject, text, html, reply_to=data["email"])
            return jsonify({"ok": True})
        except Exception as err:  # pragma: no cover - runtime SMTP
            app.logger.error("Booking email error: %s", err)
            return jsonify({"ok": False, "error": "Erreur lors de l'envoi de la réservation."}), 500

    return app


app = create_app()


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 3000))
    app.run(host="0.0.0.0", port=port)
