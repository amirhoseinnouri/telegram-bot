import { Bot } from "grammy";
import { createClient } from "@vercel/kv";

const kv = createClient({
  url: process.env.KV_REST_API_URL,
  token: process.env.KV_REST_API_TOKEN,
});

const bot = new Bot(process.env.BOT_TOKEN);

function maskEmail(email) {
  if (!email) return "";
  const parts = email.split("@");
  if (parts[0].length <= 2) return "***@" + parts[1];
  return parts[0].slice(0, 2) + "***@" + parts[1];
}

function maskString(s, keepStart = 2, keepEnd = 0) {
  if (!s) return "";
  if (s.length <= keepStart + keepEnd) return "*".repeat(s.length);
  return (
    s.slice(0, keepStart) +
    "*".repeat(s.length - keepStart - keepEnd) +
    s.slice(s.length - keepEnd)
  );
}

bot.command("start", async (ctx) => {
  await ctx.reply("👋 Welcome! Please enter your coupon code to start.");
  await kv.delete(ctx.chat.id.toString()); // clean any old session
  await kv.put(ctx.chat.id.toString(), JSON.stringify({ step: "coupon" }));
});

bot.on("message:text", async (ctx) => {
  const chatId = ctx.chat.id.toString();
  let session = {};

  try {
    const stored = await kv.get(chatId);
    if (stored) session = JSON.parse(stored);

    if (session.step === "coupon") {
      session.coupon = ctx.message.text.trim();
      session.step = "email";
      await ctx.reply("📧 Please enter your email:");
    } else if (session.step === "email") {
      session.email = ctx.message.text.trim();
      session.step = "password";
      await ctx.reply("🔑 Please enter your password:");
    } else if (session.step === "password") {
      session.password = ctx.message.text.trim(); 
      session.step = "name";
      await ctx.reply("👤 Please enter your full name:");
    } else if (session.step === "name") {
      session.name = ctx.message.text.trim();

      const response = await fetch(`${process.env.API_BASE_URL}/api/v1/order`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          coupon: session.coupon,
          email: session.email,
          password: session.password,
          name: session.name,
        }),
      });

      const result = await response.json();

      if (!result.success) {
        await ctx.reply("❌ Error creating order. Please try again.");
        await kv.delete(chatId);
        return;
      }

      session.orderId = result.data.orderId;
      session.step = "code";
      const { password, ...safeSession } = session;
      await kv.put(chatId, JSON.stringify(safeSession));

      await ctx.reply("📩 Order created. Please enter the 6-digit verification code:");
    } else if (session.step === "code") {
      const code = ctx.message.text.trim();

      const response = await fetch(
        `${process.env.API_BASE_URL}/api/v1/order/${session.orderId}/code`,
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ code }),
        }
      );

      const result = await response.json();

      if (!result.success) {
        await ctx.reply("⚠️ Invalid code. Please try again.");
        return;
      }

      const statusRes = await fetch(
        `${process.env.API_BASE_URL}/api/v1/order/${session.orderId}`
      );
      const statusData = await statusRes.json();

      if (!statusData.success) {
        await ctx.reply("⚠️ Error fetching status. Please try again later.");
        await kv.delete(chatId);
        return;
      }

      const data = statusData.data;

      let resultMessage = "✅ Order Completed:\n\n";
      resultMessage += `📧 Email: ${maskEmail(data.email)}\n`;
      resultMessage += `🔑 Password: ${maskString(session.password, 1, 1)}\n`;
      resultMessage += `👤 Name: ${maskString(data.name, 2)}\n`;
      resultMessage += `🆔 Order ID: ${data.orderId}\n`;
      resultMessage += `📦 Status: ${data.status}\n`;

      await ctx.reply(resultMessage);

      await kv.delete(chatId);
    } else {
      await ctx.reply("❓ Please start again with /start");
    }

    const { password, ...safeSession } = session;
    await kv.put(chatId, JSON.stringify(safeSession));
  } catch (e) {
    console.error("Error:", e);
    await ctx.reply("⚠️ An internal error occurred. Please try again later.");
    await kv.delete(chatId);
  }
});

bot.start();
